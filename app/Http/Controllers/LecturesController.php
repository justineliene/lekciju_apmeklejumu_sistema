<?php

namespace App\Http\Controllers;

use Acaronlex\LaravelCalendar\Calendar;
use App\Attendance;
use App\ScheduledLecture;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;


class LecturesController extends Controller
{
    public function index()
    {
        if (Auth::guard('student')->check()) {
            $lectures = ScheduledLecture::where('course_id', Auth::user()->course_id)->orderBy('date', 'ASC')->get();
        } else if (Auth::guard('lecturer')->check()) {
            $lectures = ScheduledLecture::where('lecturer_id', Auth::user()->id)->orderBy('date', 'ASC')->get();
        } else {
            $lectures = ScheduledLecture::orderBy('date', 'ASC')->get();
        }

        $events = [];

        foreach ($lectures as $lecture) {
            $events[] = Calendar::event(
                $lecture->lecture->name . ' (' . $lecture->course->name . ')',
                false,
                $lecture->startDate(),
                $lecture->endDate(),
                $lecture->id
            );
        }

        $calendar = new Calendar();
        $calendar->addEvents($events)
            ->setOptions([
                'locale' => 'lv',
                'displayEventTime' => true,
                'displayEventEnd' => true,
                'selectable' => true,
                'initialView' => 'dayGridMonth'
            ]);

        $calendar->setCallbacks([
            'select' => 'function(selectionInfo){}',
            'eventClick' => 'function(event) {
                $("#lecture_id").val(event.event._def.publicId);
                $("#editModal").modal();
                $("#deleteModal").modal();
            }'
        ]);


        return view('lectures', compact('calendar'))->with('lectures', $lectures);
    }

    public function checkAttendance()
    {
        if (Auth::guard('student')->check()) {
            if (!Attendance::where('scheduled_lecture_id', request('lecture_id'))->where('student_id', Auth::user()->id)->exists()) {
                $sc_lecture = ScheduledLecture::find(request('lecture_id'));
                $now = Carbon::now();
                $start = Carbon::createFromTimeString($sc_lecture->start_at);
                $end = Carbon::createFromTimeString($sc_lecture->end_at)->addDay();

                if ($now->between($start, $end)) {
                    $student = Auth::user();
                    $attendance = new Attendance;
                    $attendance->scheduled_lecture_id = request('lecture_id');
                    $attendance->student_id = $student->id;
                    $attendance->save();
                    return back()->with('success', 'Apmekl??jums atz??m??ts!');
                } else {
                    return back()->with('error', "Lekcija ??obr??d nenotiek!");
                }
            } else {
                return back()->with('error', "J??s jau esat atz??m??jies!");
            }
        } else {
            return back()->with('error', "J??s neesat students!");
        }
    }

    public function deleteScheduledLecture()
    {
        ScheduledLecture::find(request('lecture_id'))->delete();
        return back()->with('success', "Lekcija veiksm??gi dz??sta!");
    }
}
?>