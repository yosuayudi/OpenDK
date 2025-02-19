<?php

/*
 * File ini bagian dari:
 *
 * OpenDK
 *
 * Aplikasi dan source code ini dirilis berdasarkan lisensi GPL V3
 *
 * Hak Cipta 2017 - 2022 Perkumpulan Desa Digital Terbuka (https://opendesa.id)
 *
 * Dengan ini diberikan izin, secara gratis, kepada siapa pun yang mendapatkan salinan
 * dari perangkat lunak ini dan file dokumentasi terkait ("Aplikasi Ini"), untuk diperlakukan
 * tanpa batasan, termasuk hak untuk menggunakan, menyalin, mengubah dan/atau mendistribusikan,
 * asal tunduk pada syarat berikut:
 *
 * Pemberitahuan hak cipta di atas dan pemberitahuan izin ini harus disertakan dalam
 * setiap salinan atau bagian penting Aplikasi Ini. Barang siapa yang menghapus atau menghilangkan
 * pemberitahuan ini melanggar ketentuan lisensi Aplikasi Ini.
 *
 * PERANGKAT LUNAK INI DISEDIAKAN "SEBAGAIMANA ADANYA", TANPA JAMINAN APA PUN, BAIK TERSURAT MAUPUN
 * TERSIRAT. PENULIS ATAU PEMEGANG HAK CIPTA SAMA SEKALI TIDAK BERTANGGUNG JAWAB ATAS KLAIM, KERUSAKAN ATAU
 * KEWAJIBAN APAPUN ATAS PENGGUNAAN ATAU LAINNYA TERKAIT APLIKASI INI.
 *
 * @package    OpenDK
 * @author     Tim Pengembang OpenDesa
 * @copyright  Hak Cipta 2017 - 2022 Perkumpulan Desa Digital Terbuka (https://opendesa.id)
 * @license    http://www.gnu.org/licenses/gpl.html    GPL V3
 * @link       https://github.com/OpenSID/opendk
 */

namespace App\Providers;

use App\Models\DataDesa;
use App\Models\DataUmum;
use App\Models\Penduduk;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // default lengt string
        Schema::defaultStringLength(191);
        $this->penduduk();
        $this->config();
    }

    protected function penduduk()
    {
        Penduduk::saved(function ($model) {
            $dataUmum = DataUmum::where('kecamatan_id', $model->kecamatan_id)->first();

            $dataUmum->jumlah_penduduk    = $model->where('kecamatan_id', $model->kecamatan_id)->count();
            $dataUmum->jml_laki_laki      = $model->where('sex', 1)->count();
            $dataUmum->jml_perempuan      = $model->where('sex', 2)->count();
            $dataUmum->luas_wilayah       = DataDesa::where('kecamatan_id', $model->kecamatan_id)->sum('luas_wilayah');
            $dataUmum->kepadatan_penduduk = $dataUmum->luas_wilayah == 0 ? 0 : $dataUmum->jumlah_penduduk / $dataUmum->luas_wilayah;

            $dataUmum->save();
        });

        Penduduk::deleted(function ($model) {
            $dataUmum = DataUmum::where('kecamatan_id', $model->kecamatan_id)->first();

            $dataUmum->jumlah_penduduk    = $model->where('kecamatan_id', $model->kecamatan_id)->count();
            $dataUmum->jml_laki_laki      = $model->where('sex', 1)->count();
            $dataUmum->jml_perempuan      = $model->where('sex', 2)->count();
            $dataUmum->luas_wilayah       = DataDesa::where('kecamatan_id', $model->kecamatan_id)->sum('luas_wilayah');
            $dataUmum->kepadatan_penduduk = $dataUmum->luas_wilayah == 0 ? 0 : $dataUmum->jumlah_penduduk / $dataUmum->luas_wilayah;

            $dataUmum->save();
        });

        Validator::extend('nik_exists', function ($attribute, $value, $parameters) {
            $query = DB::table('das_penduduk')->
                where('nik', $value)->whereRaw("tanggal_lahir = '" . $parameters[0] . "'")->exists();

            if ($query) {
                return true;
            }
            return false;
        });

        Validator::extend('password_exists', function ($attribute, $value, $parameters) {
            $query = DB::table('das_penduduk')->
            where('tanggal_lahir', $value)->whereRaw("nik = '" . $parameters[0] . "'")->exists();

            if ($query) {
                return true;
            }
            return false;
        });

        Validator::extend('unique_key', function ($attribute, $value, $parameters) {
            $query = DB::table($parameters[0])
                ->where('key', $value)
                ->first();

            if (!$query || $query->id == $parameters[1]) {
                return true;
            }
            return false;
        });

        Validator::extend('valid_json', function ($attributes, $value, $parameters) {
            if (!is_string($value)) {
                return false;
            }
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }
            return true;
        });
    }

    protected function config()
    {
        config([
            'setting' => Cache::remember('setting', 24 * 60 * 60, function () {
                return  Schema::hasTable('das_setting')
                ? DB::table('das_setting')
                    ->get(['key', 'value'])
                    ->keyBy('key')
                    ->transform(function ($setting) {
                        return $setting->value;
                    })
                    : null;
            }),

            'profil' => Cache::remember('profil', 24 * 60 * 60, function () {
                if (Schema::hasTable('das_profil')) {
                    $profil = DB::table('das_profil')
                        ->get()
                        ->map(function ($item) {
                            return (array) $item;
                        })
                        ->first() ?? null;

                    if ($profil) {
                        if (in_array($profil['provinsi_id'], [91, 92])) {
                            $profil['sebutan_wilayah'] = 'Distrik';
                            $profil['sebutan_kepala_wilayah'] = 'Kepala Distrik';
                        } else {
                            $profil['sebutan_wilayah'] = 'Kecamatan';
                            $profil['sebutan_kepala_wilayah'] = 'Camat';
                        }
                    }

                    return $profil;
                }

                return null;
            }),
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
