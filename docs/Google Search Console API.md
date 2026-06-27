📂 Google Search Console API 批量查词技术文档

一、 核心结论：关于费用与权限

\1. 费用判定：100% 终身免费

- **没有任何隐藏费用**：开通、调用、批量下载数据全流程 **0 元**。
- **无需绑定信用卡**：不像谷歌地图（Maps）或翻译（Translation）接口，该 API 没有任何超出额度扣费的陷阱，对站长完全免费开放。

\2. 权限规则：只能查“自己验证过”的网站

- **核心铁律**：你**无法**使用这个接口去查竞争对手或别人的网址 [↩]。
- **多站管理**：你只需要一个谷歌账号（Gmail）和一个 API 接口通道。只要这些网站在你的同一个 Gmail 账号下通过了所有权验证，这一个 API 就能查到名下所有网站的高频词。

------

二、 接口能获取哪些“高频词”有效信息？

通过 API 接口，你可以直接突破网页端只能导出 1000 行数据的限制，一口气拉取几万条底层隐私数据：

1. **高频搜索词（Query）**：用户在谷歌上到底输入了哪些精准词、长尾词或错别字才搜到你。
2. **点击量（Clicks）**：这个高频词实实在在为你的网站带去了多少个**真实进站点击**。
3. **曝光量（Impressions）**：你的网站在这个词的搜索结果里露脸了多少次。
4. **平均排名（Position）**：精准到小数点后一位（如 1.5 名），告诉你网站在这个高频词下的具体位置。
5. **落地页（Page）**：用户搜了这个词后，最终点进了你网站的**哪一个具体网址（URL）**。

------

三、 API 接口的具体使用方法（多站点查询）

谷歌 API 的底层运行逻辑是：**“同一个接口管道，一问一答，一站一查”**。你不需要为 A 网站和 B 网站申请不同的接口，只需要在发送请求时，通过**循环更换网址参数**，接口就会源源不断地把各个站点的数据分别吐给你。

核心请求示例（底层逻辑）

不论你用什么语言，你向谷歌统一的接口地址发送请求时，数据包长这样：

🔍 第一次请求（查 A 网站）：

- **接口通用地址**：`https://googleapis.com`

- **发送数据包**：

  json

  ```
  {
    "startDate": "2026-05-26",
    "endDate": "2026-06-26",
    "dimensions": ["query", "page"],
    "rowLimit": 10000
  }
  ```

  请谨慎使用此类代码。

  

- **接口返回**：**A 网站**专属的前 10000 个高频词、点击量、对应落地页。

🔍 第二次请求（查 B 网站）：

- **接口通用地址**：`https://googleapis.com`

- **发送数据包**：（完全相同的格式）

  json

  ```
  {
    "startDate": "2026-05-26",
    "endDate": "2026-06-26",
    "dimensions": ["query", "page"],
    "rowLimit": 10000
  }
  ```

  请谨慎使用此类代码。

  

- **接口返回**：**B 网站**专属的前 10000 个高频词、点击量。

------

四、 怎么使用？（两种落地实操方案）

方案 A：0 代码流 —— 使用 Google Sheets 插件（强烈推荐）

如果你不想写代码，谷歌表格有一款官方认证的插件，它在后台帮你把上面的 API 请求全部自动写好了：

1. **绑定网站**：把 A 网站和 B 网站在 [Google Search Console 网页端](https://search.google.com/search-console/about) 验证通过。
2. **新建表格**：打开一个空白的 **Google Sheets（谷歌电子表格）**。
3. **安装插件**：点击顶部菜单 `扩展程序` -> `获取附加程序` -> 搜索并安装 **`Search Analytics for Sheets`**。
4. **授权登录**：使用你管理 A、B 网站的同一个 Gmail 账号授权登录。
5. **一键多站切换**：在右侧控制面板的 **Verified Site（已验证网址）** 下拉菜单中，你可以自由切换 A 站或 B 站，点击 **Request Data**，几万行高频词和点击量就会一秒平铺在表格里。

------

方案 B：程序员流 —— 使用 Python 自动化脚本

如果你追求完全自动化，可以运行以下标准的 Python 脚本。把你要查的所有站点网址放在第 14 行的数组里，运行一次，电脑就会**自动通过一个 API 接口把所有网站的高频词分别导出为独立的 Excel 表格**：

python

```
import os
import pandas as pd
from google_auth_oauthlib.flow import InstalledAppFlow
from google.oauth2.credentials import Credentials
from googleapiclient.discovery import build

SCOPES = ['https://googleapis.com']

def batch_fetch_all_my_sites():
    # 1. 加载你的万能钥匙凭证 (需提前在 Google Cloud 下载 credentials.json)
    flow = InstalledAppFlow.from_client_secrets_file('credentials.json', SCOPES)
    creds = flow.run_local_server(port=0)
    service = build('webmasters', 'v3', credentials=creds)
    
    # 2. 【核心】：把你名下的 A、B、C 网站全部塞进这个列表里
    my_sites = [
        'https://your-website-a.com',
        'https://your-website-b.com',
        'https://your-website-c.com'
    ]
    
    # 3. 利用同一个 API 接口循环调取数据
    for site_url in my_sites:
        print(f"正在通过 API 拉取: {site_url} ...")
        
        request_body = {
            'startDate': '2026-05-26',
            'endDate': '2026-06-26',
            'dimensions': ['query', 'page'], # 只要这两个维度就能抓出高频词和对应网页
            'rowLimit': 20000 
        }
        
        # 调用同一个接口，只是传入的 siteUrl 参数不同
        response = service.searchanalytics().query(siteUrl=site_url, body=request_body).execute()
        
        # 解析并保存为本地 Excel
        rows = response.get('rows', [])
        data = [{'搜索词': r['keys'][0], '点击量': r['clicks'], '排名': r['position']} for r in rows]
        
        df = pd.DataFrame(data)
        file_name = site_url.replace('https://','').replace('/','_') + ".xlsx"
        df.to_excel(file_name, index=False)
        print(f"成功保存至: {file_name}\n")

if __name__ == '__main__':
    batch_fetch_all_my_sites()
```

请谨慎使用此类代码。



------