<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\Category;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    //お問い合わせフォームの表示
    public function index()
    {
        $categories = Category::all(); //categoriesテーブルから全てのカテゴリデータを取得
        return view('contact', compact('categories'));
    }

    //確認画面の表示
    public function confirm(ContactRequest $request)
    {
        $contacts = $request->all(); //入力されたお問い合わせ内容を全て取得
        $category = Category::find($request->category_id); //選択されたカテゴリを取得
        return view('confirm', compact('contacts', 'category')); //取得した問い合わせ内容とカテゴリをconfirmと一緒に表示
    }

    //お問い合わせデータの保存
    public function store(ContactRequest $request)
    {
        if ($request->has('back')) {
            return redirect('/')->withInput();
        } //もし「戻る」ボタンが押された場合、入力画面に戻る。sendっていう文字はないけど、back以外という認識。

        $request['tell'] = $request->tel_1 . $request->tel_2 . $request->tel_3;
        Contact::create( //お問い合わせ内容をデータベースに保存
            $request->only([
                'category_id',
                'first_name',
                'last_name',
                'gender',
                'email',
                'tell',
                'address',
                'building',
                'detail'
            ])
        );

        return view('thanks');
    }

    //管理画面の表示
    public function admin()
    {
        $contacts = Contact::with('category')->paginate(7); //contactsテーブルからcategoryも一緒に取り出し、7件ずつ表示
        $categories = Category::all(); //categoriesテーブルから全データ取得
        $csvData = Contact::all(); //csv出力用のお問い合わせデータを全て取得
        return view('admin', compact('contacts', 'categories', 'csvData')); //上の全部ビューに渡す
    }

    //検索機能
    public function search(Request $request)
    {
        if ($request->has('reset')) { //リセットボタンが押されたら管理画面を表示
            return redirect('/admin')->withInput();
        }
        $query = Contact::query(); //contactsテーブルに問い合わせるための「質問を作る道具」のようなもの。$queryという箱に、質問を作る道具を入れる。

        $query = $this->getSearchQuery($request, $query); //ユーザーが入力した情報が入った箱($request)と、質問を作る道具が入った箱($query)を使って、データベースへの質問を組み立てる。でもう一回$queryに入れられる。

        $contacts = $query->paginate(7); //検索結果を7件ずつ表示。
        $csvData = $query->get(); //検索結果からcsv出力用データを取得
        $categories = Category::all(); //カテゴリ習得
        return view('admin', compact('contacts', 'categories', 'csvData'));
    }

    //削除機能
    public function destroy(Request $request)
    {
        Contact::find($request->id)->delete();
        return redirect('/admin');
    }

    //CSVエクスポート機能
    public function export(Request $request)
    {
        $query = Contact::query(); //contactsテーブルからデータを取得する準備。

        $query = $this->getSearchQuery($request, $query); //検索条件が指定されていればgetSearchQueryメソッドを使ってデータを絞り込む

        $csvData = $query->get()->toArray(); //絞り込んだデータを、csv配列に変換

        $csvHeader = [
            'id', 'category_id', 'first_name', 'last_name', 'gender', 'email', 'tell', 'address', 'building', 'detail', 'created_at', 'updated_at'
        ];

        $response = new StreamedResponse(function () use ($csvHeader, $csvData) { //大量のデータを効率的にダウンロードさせるための仕組み
            $createCsvFile = fopen('php://output', 'w'); //データをファイルに出力するための準備

            mb_convert_variables('SJIS-win', 'UTF-8', $csvHeader); //文字コードを変換

            fputcsv($createCsvFile, $csvHeader); //ヘッダーをCSVファイルに書き込む

            foreach ($csvData as $csv) { //データベースから取得したデータを1行ずつCSVファイルに書き込む
                $csv['created_at'] = Date::make($csv['created_at'])->setTimezone('Asia/Tokyo')->format('Y/m/d H:i:s');
                $csv['updated_at'] = Date::make($csv['updated_at'])->setTimezone('Asia/Tokyo')->format('Y/m/d H:i:s'); //日付の形式をCSVファイルに適した形式に変換
                fputcsv($createCsvFile, $csv); //データをCSVファイルに書き込む
            }

            fclose($createCsvFile); //ファイルへの書き込みを終了
        }, 200, [
            'Content-Type' => 'text/csv', //ダウンロードさせるファイルの種類をCSVファイルと指定
            'Content-Disposition' => 'attachment; filename="contacts.csv"', //ダウンロードさせるファイル名を指定
        ]);

        return $response; //CSVファイルのダウンロードを開始
    }

    //検索条件の組み立て
    private function getSearchQuery($request, $query)
    {
        if(!empty($request->keyword)) { //検索キーワードが入力されていれば、以下の条件を追加
            $query->where(function ($q) use ($request) { //以下の検索条件をグループ化する
                $q->where('first_name', 'like', '%' . $request->keyword . '%') //名前(苗字)にキーワードを含むデータを検索。
                    ->orWhere('last_name', 'like', '%' . $request->keyword . '%') //名前(名前)に
                    ->orWhere('email', 'like', '%' . $request->keyword . '%'); //メアドに
            });
        }

        if (!empty($request->gender)) { //性別が選択されていれば、一致するデータを検索する
            $query->where('gender', '=', $request->gender);
        }

        if (!empty($request->category_id)) { //カテゴリ
            $query->where('category_id', '=', $request->category_id);
        }

        if (!empty($request->date)) { //日付
            $query->whereDate('created_at', '=', $request->date);
        }

        return $query; //組み立てた検索条件を返す。
    }
}
