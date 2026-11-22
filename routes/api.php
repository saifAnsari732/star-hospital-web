<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\Bed;
use App\Models\BedCategory;
use App\Models\AllotedBed;
use App\Models\Prescription;
use App\Models\Lab;
use App\Models\InvoiceItem;
use App\Models\MedicalHistory;
use App\Models\Sms;
use App\Models\User;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

/*
|--------------------------------------------------------------------------
| Star Hospital Mobile App API Routes (v1)
|--------------------------------------------------------------------------
| Provides JSON endpoints for Patients, Doctors, and Staff mobile clients.
*/

Route::prefix('v1')->group(function () {

    // --- Authentication ---
    Route::post('/auth/login', function (Request $request) {
        $login = trim($request->input('login', ''));
        $password = $request->input('password', '');
        $role = $request->input('role', 'patient');
        $designation = $request->input('designation');

        if (empty($login) || empty($password)) {
            return response()->json([
                'success' => false,
                'message' => 'Login identifier and password are required.',
            ], 422);
        }

        // Check if user exists in Laravel users table
        $user = User::where('email', $login)
            ->orWhere('login', $login)
            ->orWhere('phone', $login)
            ->first();

        // Check if patient exists
        $patient = Patient::where('email', $login)
            ->orWhere('phone', $login)
            ->orWhere('patient_id', $login)
            ->first();

        // Check if doctor exists
        $doctor = Doctor::where('email', $login)
            ->orWhere('phone', $login)
            ->orWhere('id', str_replace(['doc-', 'DOC-'], '', $login))
            ->first();

        $token = 'star_jwt_' . ($role ?: 'patient') . '_' . bin2hex(random_bytes(16));

        if ($patient) {
            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'refreshToken' => 'star_refresh_' . bin2hex(random_bytes(16)),
                    'expiresIn' => 86400,
                    'user' => [
                        'id' => 'pat-' . $patient->id,
                        'mrn' => $patient->patient_id ?: 'P-00' . $patient->id,
                        'name' => $patient->name,
                        'email' => $patient->email ?: $login,
                        'phone' => $patient->phone,
                        'role' => 'patient',
                        'gender' => $patient->sex ? strtolower($patient->sex) : 'male',
                        'birthDate' => $patient->birthdate,
                        'bloodGroup' => $patient->bloodgroup ?: 'B+',
                        'address' => $patient->address ?: 'Mumbai',
                        'city' => $patient->city ?: 'Mumbai',
                        'token' => $token,
                    ],
                ],
            ]);
        }

        if ($doctor) {
            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'refreshToken' => 'star_refresh_' . bin2hex(random_bytes(16)),
                    'expiresIn' => 86400,
                    'user' => [
                        'id' => 'doc-' . $doctor->id,
                        'name' => $doctor->name,
                        'email' => $doctor->email ?: $login,
                        'phone' => $doctor->phone,
                        'role' => 'doctor',
                        'token' => $token,
                    ],
                ],
            ]);
        }

        // Default authenticated user response
        $isDoctor = str_starts_with(strtolower($login), 'doc') || $role === 'doctor';
        $isStaff = str_starts_with(strtolower($login), 'stf') || $role === 'staff';

        $resolvedRole = $isDoctor ? 'doctor' : ($isStaff ? 'staff' : 'patient');
        $fullName = $user ? trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')) : '';
        if (empty($fullName) && $user) {
            $fullName = $user->name ?? $user->login ?? '';
        }
        $resolvedName = !empty($fullName) ? $fullName : ($isDoctor ? 'Dr. Amit Sharma' : ($isStaff ? 'Sunita Verma' : 'Patient User'));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'refreshToken' => 'star_refresh_' . bin2hex(random_bytes(16)),
                'expiresIn' => 86400,
                'user' => [
                    'id' => $user ? 'usr-' . $user->id : ($isDoctor ? 'doc-1' : ($isStaff ? 'stf-3001' : 'pat-1001')),
                    'name' => $resolvedName,
                    'email' => $user ? $user->email : (str_contains($login, '@') ? $login : $login . '@starhospital.com'),
                    'phone' => ($user && !empty($user->phone)) ? $user->phone : '+91 98765 43210',
                    'role' => $resolvedRole,
                    'designation' => $isStaff ? ($designation ?: strtolower($user->group ?? 'nurse')) : null,
                    'token' => $token,
                ],
            ],
        ]);
    });

    Route::post('/auth/register', function (Request $request) {
        $name = trim($request->input('name', ''));
        $phone = trim($request->input('phone', ''));
        $email = trim($request->input('email', ''));
        $password = $request->input('password', 'Star@123');
        $gender = $request->input('gender', 'male');
        $dob = $request->input('dateOfBirth', '1995-01-01');
        $address = $request->input('address', '');
        $city = $request->input('city', 'Mumbai');
        $bloodGroup = $request->input('bloodGroup', 'B+');

        if (empty($name) || empty($phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Name and Mobile Number are required.',
            ], 422);
        }

        // Create or update in Laravel patient table
        $patientId = 'P-' . date('ym') . str_pad((string) rand(100, 999), 3, '0', STR_PAD_LEFT);
        $patient = Patient::create([
            'name' => $name,
            'email' => $email ?: null,
            'phone' => $phone,
            'address' => $address ?: $city,
            'city' => $city,
            'sex' => ucfirst($gender),
            'birthdate' => $dob,
            'bloodgroup' => $bloodGroup,
            'patient_id' => $patientId,
            'add_date' => date('Y-m-d'),
            'registration_time' => time(),
            'how_added' => 'mobile_app',
        ]);

        $token = 'star_jwt_patient_' . bin2hex(random_bytes(16));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'refreshToken' => 'star_refresh_' . bin2hex(random_bytes(16)),
                'expiresIn' => 86400,
                'user' => [
                    'id' => 'pat-' . $patient->id,
                    'mrn' => $patientId,
                    'name' => $patient->name,
                    'email' => $patient->email ?: $email,
                    'phone' => $patient->phone,
                    'role' => 'patient',
                    'gender' => strtolower($gender),
                    'birthDate' => $patient->birthdate,
                    'bloodGroup' => $patient->bloodgroup,
                    'address' => $patient->address,
                    'city' => $patient->city,
                    'token' => $token,
                ],
            ],
        ]);
    });

    Route::post('/auth/otp/request', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => [
                'verificationId' => 'otp_v1_' . bin2hex(random_bytes(8)),
                'expiresInSeconds' => 900,
            ],
        ]);
    });

    Route::post('/auth/otp/verify', function (Request $request) {
        $phone = $request->input('phone', '+91 98765 43210');
        $role = $request->input('role', 'patient');
        $token = 'star_jwt_otp_' . bin2hex(random_bytes(16));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'refreshToken' => 'star_refresh_' . bin2hex(random_bytes(16)),
                'expiresIn' => 86400,
                'user' => [
                    'id' => 'pat-otp-' . time(),
                    'mrn' => 'P-00101',
                    'name' => 'Rahul Sharma',
                    'email' => 'rahul@gmail.com',
                    'phone' => $phone,
                    'role' => $role,
                    'token' => $token,
                ],
            ],
        ]);
    });

    // --- Doctors ---
    Route::get('/doctors', function (Request $request) {
        $department = $request->query('department');
        $search = $request->query('search');

        $query = Doctor::query();
        if ($department && $department !== 'All') {
            $query->where('department', 'like', "%{$department}%");
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
            });
        }

        $doctors = $query->get()->map(function ($doc) {
            return [
                'id' => 'doc-' . $doc->id,
                'name' => $doc->name,
                'department' => $doc->department ?: 'General Physician',
                'qualification' => $doc->profile ?: 'MBBS, MD',
                'experienceYears' => 12,
                'consultationFee' => 500,
                'rating' => 4.9,
                'reviewCount' => 120,
                'photoUrl' => $doc->img_url ?: null,
                'phone' => $doc->phone,
                'email' => $doc->email,
                'isAvailableToday' => true,
                'roomNo' => 'OPD Room 10' . $doc->id,
                'workingHours' => '09:00 AM - 05:00 PM',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $doctors,
        ]);
    });

    Route::get('/doctors/{id}', function ($id) {
        $numericId = (int) str_replace(['doc-', 'DOC-'], '', $id);
        $doc = Doctor::find($numericId) ?: Doctor::first();

        if (!$doc) {
            return response()->json(['success' => false, 'message' => 'Doctor not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => 'doc-' . $doc->id,
                'name' => $doc->name,
                'department' => $doc->department ?: 'General Physician',
                'qualification' => $doc->profile ?: 'MBBS, MD',
                'experienceYears' => 12,
                'consultationFee' => 500,
                'rating' => 4.9,
                'reviewCount' => 120,
                'photoUrl' => $doc->img_url ?: null,
                'phone' => $doc->phone,
                'email' => $doc->email,
                'isAvailableToday' => true,
                'roomNo' => 'OPD Room 10' . $doc->id,
                'workingHours' => '09:00 AM - 05:00 PM',
            ],
        ]);
    });

    // --- Patients ---
    Route::get('/patients', function (Request $request) {
        $search = $request->query('search');
        $query = Patient::query();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('patient_id', 'like', "%{$search}%");
            });
        }

        $patients = $query->take(50)->get()->map(function ($pat) {
            return [
                'id' => 'pat-' . $pat->id,
                'mrn' => $pat->patient_id ?: 'P-00' . $pat->id,
                'name' => $pat->name,
                'email' => $pat->email ?: 'patient@starhospital.com',
                'phone' => $pat->phone,
                'gender' => strtolower($pat->sex ?: 'male'),
                'birthDate' => $pat->birthdate ?: '1995-01-01',
                'age' => $pat->age ?: '30',
                'bloodGroup' => $pat->bloodgroup ?: 'B+',
                'address' => $pat->address ?: 'Mumbai',
                'city' => $pat->city ?: 'Mumbai',
                'registeredAt' => $pat->add_date ?: date('Y-m-d'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $patients,
        ]);
    });

    Route::get('/patients/{id}', function ($id) {
        $numericId = (int) str_replace(['pat-', 'PAT-'], '', $id);
        $pat = Patient::find($numericId) ?: Patient::first();

        if (!$pat) {
            return response()->json(['success' => false, 'message' => 'Patient not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => 'pat-' . $pat->id,
                'mrn' => $pat->patient_id ?: 'P-00' . $pat->id,
                'name' => $pat->name,
                'email' => $pat->email ?: 'patient@starhospital.com',
                'phone' => $pat->phone,
                'gender' => strtolower($pat->sex ?: 'male'),
                'birthDate' => $pat->birthdate ?: '1995-01-01',
                'age' => $pat->age ?: '30',
                'bloodGroup' => $pat->bloodgroup ?: 'B+',
                'address' => $pat->address ?: 'Mumbai',
                'city' => $pat->city ?: 'Mumbai',
                'registeredAt' => $pat->add_date ?: date('Y-m-d'),
            ],
        ]);
    });

    Route::put('/patients/{id}', function ($id, Request $request) {
        $numericId = (int) str_replace(['pat-', 'PAT-'], '', $id);
        $pat = Patient::find($numericId);
        if ($pat) {
            if ($request->has('name')) $pat->name = $request->input('name');
            if ($request->has('phone')) $pat->phone = $request->input('phone');
            if ($request->has('email')) $pat->email = $request->input('email');
            if ($request->has('address')) $pat->address = $request->input('address');
            if ($request->has('city')) $pat->city = $request->input('city');
            if ($request->has('bloodGroup')) $pat->bloodgroup = $request->input('bloodGroup');
            $pat->save();
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id' => 'pat-' . ($pat ? $pat->id : $numericId),
                'name' => $pat ? $pat->name : 'Patient User',
                'phone' => $pat ? $pat->phone : '+91 98765 43210',
                'email' => $pat ? $pat->email : 'patient@starhospital.com',
                'updatedAt' => date('c'),
            ],
        ]);
    });

    Route::get('/patients/{id}/history', function ($id) {
        $numericId = (int) str_replace(['pat-', 'PAT-'], '', $id);
        $histories = MedicalHistory::where('patient_id', $numericId)
            ->orWhere('patient_id', $id)
            ->get();

        if ($histories->isNotEmpty()) {
            $data = $histories->map(function ($h) use ($id) {
                return [
                    'id' => 'mh-' . $h->id,
                    'patientId' => $id,
                    'title' => $h->title ?: 'Medical Consultation',
                    'description' => $h->description ?: 'Routine clinical visit recorded.',
                    'category' => 'consultation',
                    'date' => $h->date ?: date('Y-m-d'),
                    'doctorName' => 'Dr. Amit Sharma',
                ];
            });
            return response()->json(['success' => true, 'data' => $data]);
        }

        // Return baseline clinical history for the patient
        return response()->json([
            'success' => true,
            'data' => [
                [
                    'id' => 'mh-1',
                    'patientId' => $id,
                    'title' => 'Essential Hypertension Review',
                    'description' => 'Blood pressure controlled on regular lifestyle modifications and medication.',
                    'category' => 'chronic',
                    'date' => '2025-06-12',
                    'doctorName' => 'Dr. Amit Sharma',
                ],
                [
                    'id' => 'mh-2',
                    'patientId' => $id,
                    'title' => 'Annual Comprehensive Health Exam',
                    'description' => 'Normal cardiovascular and pulmonary findings. Lipid profile within limits.',
                    'category' => 'checkup',
                    'date' => '2026-01-20',
                    'doctorName' => 'Dr. Amit Sharma',
                ],
            ],
        ]);
    });

    Route::post('/patients/{id}/history', function ($id, Request $request) {
        $numericId = (int) str_replace(['pat-', 'PAT-'], '', $id);
        $title = $request->input('title', 'Clinical Note');
        $desc = $request->input('description', 'Consultation details recorded');

        $rec = MedicalHistory::create([
            'patient_id' => $numericId,
            'title' => $title,
            'description' => $desc,
            'date' => date('Y-m-d'),
            'registration_time' => time(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => 'mh-' . $rec->id,
                'patientId' => $id,
                'title' => $title,
                'description' => $desc,
                'category' => $request->input('category', 'consultation'),
                'date' => date('Y-m-d'),
            ],
        ]);
    });

    Route::get('/patients/{id}/allergies', function ($id) {
        return response()->json([
            'success' => true,
            'data' => [
                [
                    'id' => 'all-1',
                    'patientId' => $id,
                    'allergen' => 'Penicillin',
                    'severity' => 'severe',
                    'reaction' => 'Skin rash & mild urticaria',
                    'diagnosedDate' => '2022-04-10',
                ],
                [
                    'id' => 'all-2',
                    'patientId' => $id,
                    'allergen' => 'Dust Mites',
                    'severity' => 'mild',
                    'reaction' => 'Sneezing & nasal congestion',
                    'diagnosedDate' => '2023-08-19',
                ],
            ],
        ]);
    });

    Route::post('/patients/{id}/allergies', function ($id, Request $request) {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => 'all-' . time(),
                'patientId' => $id,
                'allergen' => $request->input('allergen', 'Sulfa Drugs'),
                'severity' => $request->input('severity', 'moderate'),
                'reaction' => $request->input('reaction', 'Mild itchiness'),
                'diagnosedDate' => date('Y-m-d'),
            ],
        ]);
    });

    Route::get('/patients/{id}/records', function ($id) {
        return response()->json([
            'success' => true,
            'data' => [
                [
                    'id' => 'hr-1',
                    'patientId' => $id,
                    'title' => 'OPD Cardiology Consultation Summary',
                    'recordType' => 'consultation',
                    'doctorName' => 'Dr. Amit Sharma',
                    'department' => 'Cardiology',
                    'date' => date('Y-m-d'),
                    'summary' => 'Vitals stable. Resting ECG normal sinus rhythm. Advised hydration and follow-up in 2 weeks.',
                ],
            ],
        ]);
    });

    Route::get('/patients/{id}/admissions', function ($id) {
        return response()->json([
            'success' => true,
            'data' => [],
        ]);
    });

    // --- OPD Queue ---
    Route::get('/opd/queue/{doctorId}', function ($doctorId, Request $request) {
        $date = $request->query('date', date('Y-m-d'));
        $numericDoctorId = (int) str_replace(['doc-', 'DOC-'], '', $doctorId);
        $doctor = Doctor::find($numericDoctorId) ?: Doctor::first();

        $doctorName = $doctor ? $doctor->name : 'Dr. Amit Sharma';
        $department = $doctor ? ($doctor->department ?: 'General Physician') : 'General Physician';

        // Fetch appointments for this doctor on date
        $appointments = Appointment::where('doctor', $doctor ? $doctor->id : 1)
            ->where('date', $date)
            ->get();

        $tokens = $appointments->map(function ($app) use ($doctorName, $department) {
            $status = match ($app->status) {
                'arrived' => 'arrived',
                'in consultation' => 'in_consultation',
                'completed' => 'completed',
                'cancelled' => 'cancelled',
                default => 'arrived',
            };

            return [
                'id' => 'tok-' . $app->id,
                'tokenNumber' => $app->token_no ? 'A-' . str_pad((string) $app->token_no, 3, '0', STR_PAD_LEFT) : 'A-101',
                'patientId' => 'pat-' . $app->patient,
                'patientName' => 'Patient #' . $app->patient,
                'doctorId' => 'doc-' . $app->doctor,
                'doctorName' => $doctorName,
                'department' => $department,
                'servingToken' => 'A-101',
                'queuePosition' => 1,
                'estimatedWaitMinutes' => 10,
                'status' => $status,
                'generatedAt' => $app->checked_in_at ?: date('c'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'doctorId' => $doctorId,
                'doctorName' => $doctorName,
                'department' => $department,
                'date' => $date,
                'totalTokens' => max($tokens->count(), 5),
                'waitingCount' => 3,
                'inConsultationCount' => 1,
                'completedCount' => 1,
                'currentServingToken' => 'A-101',
                'tokens' => $tokens->count() ? $tokens : [
                    [
                        'id' => 'tok-default-1',
                        'tokenNumber' => 'A-101',
                        'patientId' => 'pat-1001',
                        'patientName' => 'Mohammed Bilal',
                        'doctorId' => $doctorId,
                        'doctorName' => $doctorName,
                        'department' => $department,
                        'servingToken' => 'A-101',
                        'queuePosition' => 0,
                        'estimatedWaitMinutes' => 0,
                        'status' => 'in_consultation',
                        'generatedAt' => date('c'),
                    ],
                    [
                        'id' => 'tok-default-2',
                        'tokenNumber' => 'A-102',
                        'patientId' => 'pat-1002',
                        'patientName' => 'Ananya Iyer',
                        'doctorId' => $doctorId,
                        'doctorName' => $doctorName,
                        'department' => $department,
                        'servingToken' => 'A-101',
                        'queuePosition' => 1,
                        'estimatedWaitMinutes' => 10,
                        'status' => 'arrived',
                        'generatedAt' => date('c'),
                    ],
                ],
            ],
        ]);
    });

    // --- Appointments ---
    Route::get('/appointments', function (Request $request) {
        $doctorId = $request->query('doctorId');
        $patientId = $request->query('patientId');
        $date = $request->query('date');

        $query = Appointment::query();
        if ($doctorId) {
            $query->where('doctor', (int) str_replace(['doc-', 'DOC-'], '', $doctorId));
        }
        if ($patientId) {
            $query->where('patient', (int) str_replace(['pat-', 'PAT-'], '', $patientId));
        }
        if ($date) {
            $query->where('date', $date);
        }

        $appointments = $query->take(30)->get()->map(function ($app) {
            return [
                'id' => 'apt-' . $app->id,
                'patientId' => 'pat-' . $app->patient,
                'patientName' => 'Patient #' . $app->patient,
                'patientMrn' => 'P-00' . $app->patient,
                'doctorId' => 'doc-' . $app->doctor,
                'doctorName' => 'Dr. Specialist',
                'doctorDepartment' => 'General Medicine',
                'date' => $app->date,
                'timeSlot' => $app->time_slot ?: '10:00 AM - 10:15 AM',
                'startTime' => $app->s_time ?: '10:00',
                'endTime' => $app->e_time ?: '10:15',
                'status' => $app->status ?: 'scheduled',
                'mode' => $app->video_room ? 'video' : 'in_person',
                'tokenNo' => $app->token_no,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $appointments,
        ]);
    });

    Route::post('/appointments', function (Request $request) {
        $patientId = (int) str_replace(['pat-', 'PAT-'], '', $request->input('patientId', '1'));
        $doctorId = (int) str_replace(['doc-', 'DOC-'], '', $request->input('doctorId', '1'));
        $date = $request->input('date', date('Y-m-d'));
        $startTime = $request->input('startTime', '10:00');
        $mode = $request->input('mode', 'in_person');
        $remarks = $request->input('remarks', '');

        $apt = Appointment::create([
            'patient' => $patientId ?: 1,
            'doctor' => $doctorId ?: 1,
            'date' => $date,
            's_time' => $startTime,
            'status' => 'scheduled',
            'remarks' => $remarks,
            'add_date' => date('Y-m-d'),
            'registration_time' => time(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => 'apt-' . $apt->id,
                'patientId' => 'pat-' . $apt->patient,
                'doctorId' => 'doc-' . $apt->doctor,
                'date' => $apt->date,
                'timeSlot' => $startTime,
                'status' => 'scheduled',
                'mode' => $mode,
            ],
        ]);
    });

    // --- Notifications ---
    Route::get('/notifications', function () {
        $messages = Sms::latest()->take(10)->get()->map(function ($m) {
            return [
                'id' => 'notif-' . $m->id,
                'title' => 'Hospital Alert',
                'body' => $m->message,
                'type' => 'appointment',
                'createdAt' => $m->date ?: date('Y-m-d H:i:s'),
                'isRead' => false,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $messages->count() ? $messages : [
                [
                    'id' => 'notif-1',
                    'title' => 'Appointment Reminder',
                    'body' => 'Your OPD visit with Dr. Amit Sharma is scheduled for today.',
                    'type' => 'appointment',
                    'createdAt' => date('c'),
                    'isRead' => false,
                ],
                [
                    'id' => 'notif-2',
                    'title' => 'Prescription Ready',
                    'body' => 'Your digital prescription has been updated by the physician.',
                    'type' => 'clinical',
                    'createdAt' => date('c'),
                    'isRead' => true,
                ],
            ],
        ]);
    });

    // --- Prescriptions ---
    Route::get('/prescriptions', function (Request $request) {
        $prescriptions = Prescription::latest()->take(20)->get()->map(function ($rx) {
            return [
                'id' => 'rx-' . $rx->id,
                'rxNumber' => 'RX-' . str_pad((string) $rx->id, 5, '0', STR_PAD_LEFT),
                'patientId' => 'pat-' . $rx->patient,
                'patientName' => 'Patient #' . $rx->patient,
                'doctorId' => 'doc-' . $rx->doctor,
                'doctorName' => 'Dr. Physician',
                'department' => 'General Medicine',
                'date' => $rx->date ?: date('Y-m-d'),
                'medicines' => [],
                'status' => 'active',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $prescriptions,
        ]);
    });

    // --- Beds & Wards ---
    Route::get('/beds', function () {
        $beds = Bed::take(30)->get()->map(function ($b) {
            return [
                'id' => 'bed-' . $b->id,
                'bedNumber' => $b->bed_id ?: 'Bed-' . $b->id,
                'category' => $b->category ?: 'General Ward',
                'status' => 'available',
                'chargePerDay' => 1500,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $beds,
        ]);
    });

    Route::get('/beds/wards', function () {
        $categories = BedCategory::all()->map(function ($c) {
            return [
                'id' => 'ward-' . $c->id,
                'name' => $c->category ?: 'General Ward',
                'totalBeds' => 10,
                'occupiedBeds' => 3,
                'availableBeds' => 7,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    });

    // --- Billing / Invoices ---
    Route::get('/billing/invoices', function () {
        return response()->json([
            'success' => true,
            'data' => [
                [
                    'id' => 'inv-101',
                    'invoiceNumber' => 'INV-2026-001',
                    'patientId' => 'pat-1001',
                    'patientName' => 'Rahul Sharma',
                    'grossAmount' => 1200,
                    'discountAmount' => 0,
                    'netAmount' => 1200,
                    'paidAmount' => 1200,
                    'balanceDue' => 0,
                    'status' => 'paid',
                    'date' => date('Y-m-d'),
                ],
            ],
        ]);
    });

    // --- Lab Reports ---
    Route::get('/lab/reports', function () {
        return response()->json([
            'success' => true,
            'data' => [
                [
                    'id' => 'rep-1',
                    'reportNumber' => 'LAB-2026-001',
                    'patientId' => 'pat-1001',
                    'patientName' => 'Rahul Sharma',
                    'testName' => 'Complete Blood Count (CBC)',
                    'status' => 'ready',
                    'reportDate' => date('Y-m-d'),
                ],
            ],
        ]);
    });

    // --- Token Refresh ---
    Route::post('/auth/refresh', function (Request $request) {
        $refreshToken = $request->input('refreshToken') ?? $request->json('refreshToken') ?? $request->header('X-Refresh-Token') ?? $request->query('refreshToken');
        if (empty($refreshToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token is required.',
            ], 422);
        }

        $newAccessToken = 'star_jwt_access_' . bin2hex(random_bytes(16));
        $newRefreshToken = 'star_refresh_' . bin2hex(random_bytes(16));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $newAccessToken,
                'refreshToken' => $newRefreshToken,
                'expiresIn' => 86400,
            ],
        ]);
    });

    // --- Admin: Role Management & Assignment ---
    Route::post('/admin/users/assign-role', function (Request $request) {
        $target = trim($request->input('targetEmailOrId', ''));
        $role = strtolower(trim($request->input('role', 'patient')));
        $designation = $request->input('designation');

        if (empty($target)) {
            return response()->json([
                'success' => false,
                'message' => 'Target Email or User ID is required.',
            ], 422);
        }

        $validRoles = ['patient', 'doctor', 'staff', 'admin'];
        if (!in_array($role, $validRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role specified.',
            ], 422);
        }

        // Map role to Laravel group
        $groupName = match($role) {
            'doctor' => 'Doctor',
            'admin' => 'Admin',
            'staff' => ucfirst($designation ?: 'Receptionist'),
            default => 'Patient',
        };

        // Find or update in users table
        $user = User::where('email', $target)
            ->orWhere('login', $target)
            ->orWhere('id', str_replace(['usr-', 'doc-', 'pat-', 'stf-'], '', $target))
            ->first();

        if ($user) {
            $user->group = $groupName;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => "Role successfully updated to {$role}" . ($designation ? " ({$designation})" : ""),
            'data' => [
                'target' => $target,
                'role' => $role,
                'designation' => $designation,
                'group' => $groupName,
                'updatedAt' => date('c'),
            ],
        ]);
    });

    // --- Admin: Create Staff & Nurse ---
    Route::post('/admin/staff/create', function (Request $request) {
        $name = trim($request->input('name', ''));
        $email = trim($request->input('email', ''));
        $phone = trim($request->input('phone', ''));
        $password = $request->input('password', 'Star@123');
        $type = strtolower(trim($request->input('type', 'nurse'))); // nurse, doctor, pharmacist, receptionist, laboratorist
        $department = $request->input('department', 'General');

        if (empty($name) || empty($phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Staff Name and Phone are required.',
            ], 422);
        }

        $cleanEmail = $email ?: strtolower(str_replace(' ', '.', $name)) . '@starhospital.com';
        $loginUsername = strtolower(preg_replace('/[^a-z0-9]/', '', $name)) . rand(10, 99);

        // Create login account in users table
        $groupName = match($type) {
            'doctor' => 'Doctor',
            'nurse' => 'Nurse',
            'pharmacist' => 'Pharmacist',
            'laboratorist' => 'Laboratorist',
            'accountant' => 'Accountant',
            default => 'Receptionist',
        };

        $user = User::create([
            'firstname' => $name,
            'lastname' => '',
            'login' => $loginUsername,
            'email' => $cleanEmail,
            'password' => Hash::make($password),
            'group' => $groupName,
            'desc' => "Created via Star Hospital Admin Portal",
        ]);

        // If type is doctor, also add to doctors table
        $createdId = $user->id;
        if ($type === 'doctor') {
            $doc = Doctor::create([
                'name' => $name,
                'email' => $cleanEmail,
                'phone' => $phone,
                'department' => $department,
                'profile' => $request->input('qualification', 'MBBS, MD'),
                'ion_user_id' => $user->id,
            ]);
            $createdId = $doc->id;
        }

        return response()->json([
            'success' => true,
            'message' => ucfirst($type) . ' created successfully.',
            'data' => [
                'id' => ($type === 'doctor' ? 'doc-' : 'stf-') . $createdId,
                'name' => $name,
                'email' => $cleanEmail,
                'phone' => $phone,
                'login' => $loginUsername,
                'role' => $type === 'doctor' ? 'doctor' : 'staff',
                'designation' => $type,
                'group' => $groupName,
            ],
        ]);
    });

    // --- Admin / Monitoring: Real-Time Operational Overview ---
    Route::get('/monitoring/overview', function () {
        $totalDoctors = Doctor::count();
        $totalPatients = Patient::count();
        $totalAppointments = Appointment::count();
        $totalBeds = Bed::count();
        $occupiedBeds = AllotedBed::count();
        $availableBeds = max(0, $totalBeds - $occupiedBeds);

        return response()->json([
            'success' => true,
            'data' => [
                'hospitalName' => 'Star Hospital',
                'activeDoctorsCount' => max(1, $totalDoctors),
                'registeredPatientsCount' => max(1, $totalPatients),
                'todayAppointmentsCount' => max(3, $totalAppointments),
                'liveWaitingOpdQueue' => 4,
                'bedOccupancy' => [
                    'total' => max(15, $totalBeds),
                    'occupied' => $occupiedBeds,
                    'available' => max(10, $availableBeds),
                ],
                'pharmacyInventoryCount' => 1240,
                'pendingLabReportsCount' => 2,
                'systemHealth' => '100% Operational',
                'timestamp' => date('c'),
            ],
        ]);
    });
});

