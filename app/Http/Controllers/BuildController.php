<?php

namespace App\Http\Controllers;

use App\Model\BuildLevel;
use App\Model\BuildProcess;
use App\Model\FarmLevel;
use App\Model\MapField;
use App\Model\MapFieldResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Model\MapFieldBuild;
use App\Model\MapFieldFarm;
use App\Model\MapFieldEntity;

class BuildController extends Controller
{
    public function build_farm(MapFieldFarm $farm)
    {
    	return $this->build($farm);
    }

    public function build_construction(MapFieldBuild $build)
    {
    	return $this->build($build);
    }

    private function build(MapFieldEntity $entity)
    {
        $max_queue_processes = 3;
        /** @var MapField $map_field */
        $map_field = auth()->user()->map_fields()->first();
        if ($map_field->build_processes->count() < $max_queue_processes) {
            switch (get_class($entity)) {
                case MapFieldBuild::class:
                    $level_class = BuildLevel::class;
                    break;
                case MapFieldFarm::class:
                    $level_class = FarmLevel::class;
                    break;
                default:
                    return redirect()->back();
            }
            $build_level = get_class($entity) === MapFieldBuild::class ? $entity->build_level : $entity->farm_level;
            $build_type = get_class($entity) === MapFieldBuild::class ? 'build' : 'farm';
            $next_level_build = $next_level_build_process = $level_class::with(['time', 'resources'])
                ->where($build_type.'_id', $entity->id)
                ->where('level', $build_level->level+1)
                ->first();
            $resources = $map_field->resources;
            $values = [];
            /** @var MapFieldResource $resource */
            foreach ($resources as $resource) {
                $values[$resource->game_resource_id] = $next_level_build->resources->where('game_resource_id', $resource->game_resource_id)->first()->value;
                if ((int)$resource->value < (int)$values[$resource->game_resource_id]) {
                    return redirect()->back();
                }

            }
            foreach ($resources as $resource) {
                $resource->value -= (int)$values[$resource->game_resource_id];
                $resource->save();
            }

            $start_time = Carbon::now();
            /** @var BuildProcess $build_process */
            foreach ($map_field->build_processes as $build_process) {
                $build = (new $build_process->build_type)->find($build_process->build_id);
                $build_type_process = get_class($build) === MapFieldBuild::class ? 'build' : 'farm';
                $build_level_process = $build_process->build_type === MapFieldBuild::class ? $build->build_level : $build->farm_level;
                $next_level_build_process = $level_class::with(['time'])
                    ->where($build_type_process.'_id', $build_level_process->build_id)
                    ->where('level', $build_level_process->level+1)
                    ->first();

                $start_time->addSeconds(Carbon::parse($build_process->start_time)->addSeconds($next_level_build_process->time->time)->diffInSeconds(Carbon::now())+1);
            }

        	$process = $map_field->build_processes()->create([
        		'build_id' => $entity->id,
        		'build_type' => get_class($entity),
        		'progress' => 0,
        		'status' => true,
                'start_time' => $start_time
        	]);
        	$map_field->build_processes->push($process);
        }
        return redirect()->back();
    }
}
