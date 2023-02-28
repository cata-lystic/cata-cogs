import discord
from redbot.core import Config, commands, checks
import aiohttp
import html


class Thoughts(commands.Cog):
    """Get and submit random thoughts."""

    async def red_delete_data_for_user(self, **kwargs):
        """ Nothing to delete """
        return

    def __init__(self, bot):
        self.bot = bot
        self.config = Config.get_conf(self, identifier=337404337182, force_registration=True)
        self.author = ['Catalyst']
        self.versionbot = 1.0 # Bot version
        self.versionapi = 1.0 # API version
        self.versionweb = 1.0 # Web version (when bot was last updated)
        default_global = {
            "url": "",
            "token": ""
        }
        default_guild = {
            "test": ""
        }
        self.config.register_global(**default_global)
        self.config.register_guild(**default_guild)

    async def changeSetting(self, ctx, key1, key2, newVal=''):
        if key2 == 'token':
            await self.config.token.set(newVal)
            return await ctx.send("Token Set! Please delete your last message.")
        
        if (key2 != '' and newVal != ''):

            current_token = await self.config.token()
            newVal = newVal.replace("#", "HASHTAG")
            try:    
                async with aiohttp.request("GET", "https://thoughts.frwd.app/api/config/?key1="+key1+"&key2="+key2+"&val="+newVal+"&token="+str(current_token), headers={"Accept": "text/plain"}) as r:
                    if r.status != 200:
                        return await ctx.send("Oops! Cannot change setting...")
                    result = await r.text(encoding="UTF-8")
            except aiohttp.ClientConnectionError:
                return await ctx.send("Oops! Cannot change setting...")

            await ctx.send(f"{result}")

    @commands.command(aliases=['thoughts'])
    async def thought(self, ctx, query="", limit=3, shuffle=1, showID=0):
        """Gets a thought.

        **.thought** - Get random thought
        **.thought list** - Show link to all thoughts
        **.thought 25** - Show thought by ID #
        **.thought word** - Search single word
        **.thought "multiple words"** - Search multiple words

        Create a thought.
        **.tcreate your thought here**
        """

        current_token = await self.config.token()
        if current_token == '':
            await ctx.send("You need to set an API Token. Type `.tset api token`")
            await ctx.send("You need to set an API URL. Type `.tset api url`")
        else :

            try:
                async with aiohttp.request("GET", "https://thoughts.frwd.app?q="+query+"&limit="+str(limit)+"&shuffle="+str(shuffle)+"&showID="+str(showID)+"&platform=discord&api="+str(self.versionapi), headers={"Accept": "text/plain"}) as r:
                    if r.status != 200:
                        return await ctx.send("Oops! Cannot get a thought...")
                    result = await r.text(encoding="UTF-8")
            except aiohttp.ClientConnectionError:
                return await ctx.send("Oops! Cannot get a thought...")

            await ctx.send(f"{result}")

    @commands.command(aliases=['tcreate'])
    async def thoughtcreate(self, ctx, tag: str, *, msg: str):
        """Create a thought
        ***.tcreate your thought here"""
        if tag != 'thought' and tag != 'music' and tag != 'spam':
            tMsg = html.escape(tag+" "+msg)
            tag = 'thought'
        else:
            tMsg = html.escape(msg)

        current_token = await self.config.token()

        tAuthor = html.escape(str(ctx.message.author))
        tAuthorID = html.escape(str(ctx.message.author.id))

        # Encode msg and author to base64
        #tMsg = tMsg.encode("utf8")
        #tBaseString = base64.b64encode(tMsg).decode("utf8")
        tBaseString = tMsg.replace("#", "HASHTAG")

        #tAuthor = tAuthor.encode("utf8")
        #tABaseString = base64.b64encode(tAuthor).decode("utf8")
        tABaseString = tAuthor.replace("#", "HASHTAG")

        try:    
            async with aiohttp.request("GET", "https://thoughts.frwd.app/api/create/?token="+current_token+"&platform=discord&authorID="+str(tAuthorID)+"&tag="+tag+"&msg="+tBaseString+"&author="+tABaseString, headers={"Accept": "text/plain"}) as r:
                if r.status != 200:
                    return await ctx.send("Oops! Cannot create a thought...")
                result = await r.text(encoding="UTF-8")
        except aiohttp.ClientConnectionError:
            return await ctx.send("Oops! Cannot create a thought...")
        
        await ctx.send(f"{result}")

    @commands.group(name="thoughtset", aliases=['tset'])
    @checks.is_owner()
    async def thoughtset(self, ctx):
        """Thought Settings"""

        #current_domain = await self.config.url()
        #current_token = await self.config.token()

        #await ctx.send("Current Domain: "+str(current_domain)+"\nCurrent Token: "+str(current_token))

           # await self.changeSetting(ctx, newKey, newVal)
    
    @thoughtset.group(name='api')
    async def ts_api(self, ctx):
        """API Settings
        \rThese settings are required for the bot to operate.\r\r
        The API Token is what you set in the config.php file of your website.\r\r
        The API URL points the bot to your website and API."""
        pass

    @thoughtset.group(name='bot')
    async def ts_bot(self, ctx):
        """Bot Settings
        \rThese settings will change the defaults for when thoughts are retrieved from the bot.\r\r
        To change website settings
        `.tset web`"""
        pass

    @thoughtset.group(name='web', aliases=['website'])
    async def ts_web(self, ctx):
        """Website Settings
        \rThese settings will change settings directly on your website\r\r
        To change bot settings
        `.tset bot`"""
        pass

    @ts_api.command(name='token')
    async def ts_token(self, ctx, newToken):
        """Set API token
        \rA token is required for this cog to function.
        Set your token in the config.php file of the web interface.\r\r
        Once finished, set the token
        `.tset api token tokenGoesHere`\r\r
        Finally, set the API URL
        `.tset url`"""

        await self.changeSetting(ctx, 'api', 'token', newToken)

    @ts_api.command(name='url')
    async def ts_url(self, ctx, newURL):
        """Set API URL
        \rSetting the URL lets the bot know how to reach the Thoughts API.\r\r
        Example:
        `.tset api url https://mysite.com/thoughts`"""
        await self.changeSetting(ctx, 'api', 'url', newURL)

    @ts_web.command(name='shuffle')
    async def ts_web_shuffle(self, ctx, binary):
        """Shuffle search results by default
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'shuffle', binary)

    @ts_web.command(name='showid')
    async def ts_web_showid(self, ctx, binary):
        """Show IDs in search results
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'showID', binary)

    @ts_web.command(name='quotes')
    async def ts_web_quotes(self, ctx, quotes):
        """Quotes around each thought"""
        await self.changeSetting(ctx, 'web', 'quotes', quotes)

    @ts_web.command(name='themebg', aliases=['bgcolor', 'backgroundcolor'])
    async def ts_web_bgcolor(self, ctx, color):
        """Background color
        \rWebsite's background color.
        You can use anything the CSS color tag supports\r
        Example: `.tset web bgcolor #212121`"""
        await self.changeSetting(ctx, 'web', 'backgroundColor', color)

    @ts_web.command(name='themeaccent', aliases=['accent', 'accentcolor'])
    async def ts_web_accentcolor(self, ctx, color):
        """Accent color
        \rWebsite's box color for API info, search, creation.
        You can use anything the CSS color tag supports\r
        Example: `.tset web themeaccent #393939`"""
        await self.changeSetting(ctx, 'web', 'accentColor', color)

    @ts_web.command(name='themefont', aliases=['fontcolor'])
    async def ts_web_fontcolor(self, ctx, color):
        """Font color
        \rWebsite's font color.
        You can use anything the CSS color tag supports\r
        Example: `.tset web themefont #e9e5e5`"""
        await self.changeSetting(ctx, 'web', 'accentColor', color)

    @ts_web.command(name='themeradius', aliases=['accentradius'])
    async def ts_web_accentradius(self, ctx, radius):
        """Accent box border radius
        \rWebsite's box border radius (roundness)
        You can use anything the CSS border-radius tag supports\r
        Example: `.tset web themeradius 10px`"""
        await self.changeSetting(ctx, 'web', 'accentRadius', radius)

    @ts_web.command(name='create', aliases=['creation'])
    async def ts_web_create(self, ctx, binary):
        """Enable Create Box
        \rAllow thought creation on the website.
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'create', binary)

    @ts_web.command(name='createvisible')
    async def ts_web_createvis(self, ctx, binary):
        """Show create box by default
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'createVisible', binary)

    @ts_web.command(name='createflood')
    async def ts_web_createflood(self, ctx, time):
        """Creation flood time limit 
        \rSet how long between creating posts a user has to wait on the website.\r\r
        Examples: 1s, 3m, 5d, 7w
        10 minutes would be `.tset web createflood 10m`\r\r
        This is separate from the flood limit on your bot users."""
        await self.changeSetting(ctx, 'web', 'createFlood', time)

    @ts_web.command(name='info')
    async def ts_web_info(self, ctx, binary):
        """Enable API info box
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'info', binary)

    @ts_web.command(name='infovisible')
    async def ts_web_infovis(self, ctx, binary):
        """Show API info box by default
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'infoVisible', binary)

    @ts_web.command(name='search')
    async def ts_web_search(self, ctx, binary):
        """Enable search box
        \rAllow searching on the website.
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'search', binary)

    @ts_web.command(name='searchvisible')
    async def ts_web_searchvis(self, ctx, binary):
        """Show search box by default
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'searchVisible', binary)

    @ts_web.command(name='searchlimit')
    async def ts_web_searchlimit(self, ctx, newLimit):
        """Max search results that can appear on the website
        \rExample: `.tset searchlimit 500"""
        await self.changeSetting(ctx, 'web', 'searchLimit', newLimit)

    @ts_web.command(name='searchresults')
    async def ts_web_searchresults(self, ctx, newLimit):
        """Default amount of search results
        \rExample: `.tset searchresults 50"""
        await self.changeSetting(ctx, 'web', 'searchResults', newLimit)

    @ts_web.command(name='github')
    async def ts_web_github(self, ctx, binary):
        """Show link to Thoughts GitHub
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'github', binary)

    @ts_web.command(name='js', aliases=['javascript'])
    async def ts_web_js(self, ctx, binary):
        """Allow JavaScript for searching and other features
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'js', binary)

    @ts_web.command(name='jquery')
    async def ts_web_jquery(self, ctx, binary):
        """jQuery.js location
        \rYou can choose where your jquery.js file is hosted.
        Built in options: `local` (default), `google`, `jquery`, `microsoft`, `cdnjs`, `jsdelivr`
        You can also use a custom URL"""
        await self.changeSetting(ctx, 'web', 'jquery', binary)