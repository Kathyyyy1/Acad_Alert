<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DepartmentIsolationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Skip if user is not authenticated
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // For Master Teachers
        if ($user->role === 'master_teacher') {
            $masterTeacher = DB::table('master_teachers')
                ->where('user_id', $user->id)
                ->first();

            if (!$masterTeacher) {
                return redirect()->route('dashboard')->with('error', 'Master Teacher record not found.');
            }

            $request->attributes->set('department_id', $masterTeacher->department_id);
            $request->attributes->set('master_teacher', $masterTeacher);
        }

        // FOR COUNSELORS - Added department isolation
        if ($user->role === 'guidance_counselor') {
            $counselor = DB::table('counselors')
                ->where('user_id', $user->id)
                ->first();

            if (!$counselor) {
                return redirect()->route('dashboard')->with('error', 'Counselor record not found.');
            }

            // Store department_id in request for later use
            $request->attributes->set('department_id', $counselor->department_id);
            $request->attributes->set('counselor', $counselor);
        }

        return $next($request);
    }

    public static function getDepartmentId(Request $request): ?int
    {
        return $request->attributes->get('department_id');
    }

    public static function getMasterTeacher(Request $request): ?object
    {
        return $request->attributes->get('master_teacher');
    }

    public static function getCounselor(Request $request): ?object
    {
        return $request->attributes->get('counselor');
    }
}