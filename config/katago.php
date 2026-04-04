<?php

return [
    /*
    |--------------------------------------------------------------------------
    | KataGo Binary Path
    |--------------------------------------------------------------------------
    | พาธไปยัง KataGo binary เช่น /usr/local/bin/katago หรือ /opt/katago/katago
    | ถ้าไม่ได้ตั้งค่า บอทจะใช้ PHP engine เดิมแทน
    */
    'binary' => env('KATAGO_BINARY', ''),

    /*
    |--------------------------------------------------------------------------
    | Neural Network Model
    |--------------------------------------------------------------------------
    | พาธไปยังไฟล์โมเดล KataGo (.bin.gz)
    | ดาวน์โหลดได้ที่ https://katagotraining.org/
    | แนะนำ: kata1-b18c384nbt-s9996604416-d4316597426.bin.gz (ระดับสูง)
    |         หรือ kata1-b6c96-s175395328-d26788732.bin.gz (เล็ก/เร็ว)
    */
    'model' => env('KATAGO_MODEL', ''),

    /*
    |--------------------------------------------------------------------------
    | KataGo Config File
    |--------------------------------------------------------------------------
    | พาธไปยังไฟล์ config ของ KataGo (.cfg)
    | ถ้าว่างเปล่า KataGo จะใช้ค่า default
    | ตัวอย่าง config: https://github.com/lightvector/KataGo/blob/master/cpp/configs/gtp_example.cfg
    */
    'config' => env('KATAGO_CONFIG', ''),

    /*
    |--------------------------------------------------------------------------
    | Strength Configuration per Bot Level
    |--------------------------------------------------------------------------
    | maxVisits                             : จำนวน MCTS visits สูงสุด
    | rootNoiseEnabled                      : เปิด Dirichlet noise ที่ root
    | rootPolicyTemperature                 : สูงขึ้น = สุ่มมากขึ้นจาก policy → อ่อนลง
    |                                         (1.0 = ปกติ, 1.5 = สุ่มพอสมควร, 2.5 = สุ่มมาก)
    | rootDirichletNoiseTotalConcentration  : ต่ำลง = noise กระจุกตัว → กดดัน policy แรงขึ้น
    |                                         (KataGo default = 10.83)
    | rootDirichletNoiseWeight              : สัดส่วน noise ที่ผสมเข้า policy
    |                                         (KataGo default = 0.25)
    |
    | หมายเหตุ: ระดับจริงขึ้นอยู่กับโมเดลที่ใช้ — โมเดลขนาดใหญ่ (b18/b40) แข็งกว่ามาก
    | ค่าเหล่านี้ออกแบบให้ gradient ชัดเจน ปรับ rootPolicyTemperature ถ้าต้องการอ่อน/แข็งกว่านี้
    */
    'levels' => [
        '8k' => [
            'maxVisits'                            => 1,
            'rootNoiseEnabled'                     => true,
            'rootPolicyTemperature'                => 2.5,
            'rootDirichletNoiseTotalConcentration' => 3.0,
            'rootDirichletNoiseWeight'             => 0.5,
        ],
        '5k' => [
            'maxVisits'                            => 2,
            'rootNoiseEnabled'                     => true,
            'rootPolicyTemperature'                => 1.8,
            'rootDirichletNoiseTotalConcentration' => 5.0,
            'rootDirichletNoiseWeight'             => 0.4,
        ],
        '2k' => [
            'maxVisits'                            => 8,
            'rootNoiseEnabled'                     => true,
            'rootPolicyTemperature'                => 1.3,
            'rootDirichletNoiseTotalConcentration' => 8.0,
            'rootDirichletNoiseWeight'             => 0.3,
        ],
        '1d' => [
            'maxVisits'             => 50,
            'rootNoiseEnabled'      => false,
            'rootPolicyTemperature' => 1.05,
        ],
        '3d' => [
            'maxVisits'             => 200,
            'rootNoiseEnabled'      => false,
            'rootPolicyTemperature' => 1.0,
        ],
    ],
];
