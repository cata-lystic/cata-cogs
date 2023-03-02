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
            "token": "",
            "deletedReason": 1,
            "deletedBy": 1,
            "apiFolder" :  "api"
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
        
        if key2 == 'folder':
            await self.config.apiFolder.set(newVal)
            return await ctx.send("API Folder set to `"+str(newVal)+"`")
        
        if key2 == 'url':
            await self.config.url.set(newVal)
            #await ctx.send("URL Set!")
        
        if (key2 != '' and newVal != ''):

            current_token = await self.config.token()
            current_url = await self.config.url()
            newVal = newVal.replace("#", "HASHTAG")
            apiFolder = await self.config.apiFolder()
            try:    
                async with aiohttp.request("GET", current_url+"/"+str(apiFolder)+"?q=config&key1="+key1+"&key2="+key2+"&val="+newVal+"&version="+str(self.versionapi)+"&versionbot="+str(self.versionbot)+"&platform=discord&token="+str(current_token), headers={"Accept": "text/plain"}) as r:
                    if r.status != 200:
                        return await ctx.send("Oops! Cannot change setting...")
                    result = await r.text(encoding="UTF-8")
            except aiohttp.ClientConnectionError:
                return await ctx.send("Oops! Cannot change setting...")

            await ctx.send(f"{result}")

    async def changeTag(self, ctx, query, tag1='', tag2=''):

        current_token = await self.config.token()
        current_url = await self.config.url()
        authorID = html.escape(str(ctx.message.author.id))
        apiFolder = await self.config.apiFolder()

        try:    
            async with aiohttp.request("GET", current_url+"/"+str(apiFolder)+"?q=tags&s="+str(query)+"&tag="+str(tag1)+"&authorID="+str(authorID)+"&rename="+str(tag2)+"&version="+str(self.versionapi)+"&versionbot="+str(self.versionbot)+"&platform=discord&token="+str(current_token), headers={"Accept": "text/plain"}) as r:
                if r.status != 200:
                    return await ctx.send("Oops! Cannot make tag request...")
                result = await r.text(encoding="UTF-8")
        except aiohttp.ClientConnectionError:
            return await ctx.send("Oops! Cannot make tag request...")

        await ctx.send(f"{result}")


    @commands.command(aliases=['thoughts'])
    async def thought(self, ctx, search="", limit=3, shuffle=1, showID=0):
        """Gets a thought.

        **.thought** - Get random thought
        **.thought list** - Show link to all thoughts
        **.thought 25** - Show thought by ID #
        **.thought word** - Search single word
        **.thought "multiple words"** - Search multiple words

        Create a thought.
        **.tcreate your thought here**
        Delete a thought
        **.tdelete id reason**
        """

        current_token = await self.config.token()
        
        if current_token == '':
            return await ctx.send("You need to set an API Token. Type `.tset setup token`")

        current_url = await self.config.url()

        if current_url == '':
            return await ctx.send("You need to set an API URL. Type `.tset setup url`")
        
        deleted_reason = await self.config.deletedReason() # whether or not to show deleted reason
        deleted_by = await self.config.deletedBy() # whether or not to show who deleted post
        apiFolder = await self.config.apiFolder()

        try:
            async with aiohttp.request("GET", current_url+"/"+str(apiFolder)+"?q=search&token="+current_token+"&s="+search+"&limit="+str(limit)+"&shuffle="+str(shuffle)+"&showID="+str(showID)+"&reason="+str(deleted_reason)+"&reasonby="+str(deleted_by)+"&platform=discord&version="+str(self.versionapi)+"&versionbot="+str(self.versionbot), headers={"Accept": "text/plain"}) as r:
                if r.status != 200:
                    return await ctx.send("Oops! Cannot get a thought...")
                result = await r.text(encoding="UTF-8")
        except aiohttp.ClientConnectionError:
            return await ctx.send("Oops! Cannot get a thought...")

        return await ctx.send(f"{result}")

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
        apiFolder = await self.config.apiFolder()

        tAuthor = html.escape(str(ctx.message.author))
        tAuthorID = html.escape(str(ctx.message.author.id))

        # Encode msg and author to base64
        #tMsg = tMsg.encode("utf8")
        #tBaseString = base64.b64encode(tMsg).decode("utf8")
        tBaseString = tMsg.replace("#", "HASHTAG")

        #tAuthor = tAuthor.encode("utf8")
        #tABaseString = base64.b64encode(tAuthor).decode("utf8")
        tABaseString = tAuthor.replace("#", "HASHTAG")

        current_url = await self.config.url()

        try:    
            async with aiohttp.request("GET", current_url+"/"+str(apiFolder+"?q=create&token="+current_token+"&platform=discord&authorID="+str(tAuthorID)+"&tag="+tag+"&msg="+tBaseString+"&version="+str(self.versionapi)+"&versionbot="+str(self.versionbot)+"&author="+tABaseString, headers={"Accept": "text/plain"}) as r:
                if r.status != 200:
                    return await ctx.send("Oops! Cannot create a thought...")
                result = await r.text(encoding="UTF-8")
        except aiohttp.ClientConnectionError:
            return await ctx.send("Oops! Cannot create a thought...")
        
        await ctx.send(f"{result}")

    @commands.command(aliases=['tdel','tdelete'])
    async def thoughtdelete(self, ctx, id: int, *, reason: str):
        """Delete a thought
        \rYou can only delete your own thought!
        Example: ***.tdelete*** thoughtID reason\r
        Note: Original post can still be seen in thoughts.json. To permanently delete, wipe instead:
        Example: ***.tdelete*** thoughtID wipe reason"""
        
        reason = html.escape(reason)
        current_token = await self.config.token()
        deleter = html.escape(str(ctx.message.author))
        deleter = deleter.replace("#", "HASHTAG")
        deleterID = html.escape(str(ctx.message.author.id))
        reason = reason.replace("#", "HASHTAG")
        current_url = await self.config.url()
        apiFolder = await self.config.apiFolder()

        # Note: if reason starts with 'wipe', it will be the same as adding the ?wipe=1 flag

        try:    
            async with aiohttp.request("GET", current_url+"/"+str(apiFolder)+"?q=delete&token="+current_token+"&platform=discord&id="+str(id)+"&deleterID="+str(deleterID)+"&reason="+reason+"&version="+str(self.versionapi)+"&versionbot="+str(self.versionbot)+"&deleter="+deleter, headers={"Accept": "text/plain"}) as r:
                if r.status != 200:
                    return await ctx.send("Oops! Cannot delete a thought...")
                result = await r.text(encoding="UTF-8")
        except aiohttp.ClientConnectionError:
            return await ctx.send("Oops! Cannot delete a thought...")
        
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
        \rThese settings change the default values people using your API tokens receieve.\r\r
        These are different from the default settings your bot will request.
        To access bot settings, type `.tset bot`"""
        pass

    @thoughtset.group(name='bot')
    async def ts_bot(self, ctx):
        """Bot Settings
        \rThese settings will change the defaults for when thoughts are retrieved from the bot.\r\r
        To change website settings
        `.tset web`"""
        pass

    @thoughtset.group(name='setup')
    async def ts_setup(self, ctx):
        """Setup API Token and URL
        \rThese settings are required for the bot to operate.\r\r
        The API Token the irst thing you set in the config.php file of your website.\r\r
        The API URL points the bot to your website and API."""
        pass

    @thoughtset.group(name='web', aliases=['website'])
    async def ts_web(self, ctx):
        """Website Settings
        \rThese settings will change settings directly on your website\r\r
        To change bot settings
        `.tset bot`"""
        pass

    @ts_setup.command(name='token')
    async def ts_setup_token(self, ctx, newToken):
        """Set API Token
        \rA token is required for this cog to function.
        Set your token in the config.php file of the web interface.\r\r
        Once finished, set the token
        `.tset setup token tokenGoesHere`\r\r
        Finally, set the API URL
        `.tset setup url https://yourwebsite.com`"""

        await self.changeSetting(ctx, 'api', 'token', newToken)

    @ts_setup.command(name='folder')
    async def ts_setup_folder(self, ctx, apiFolder):
        """Set API Folder
        \rIf you have moved the folder your API is in, you'll need to set it here.\r\r
        Example: `.tset setup folder yourAPIfolder`"""
        await self.changeSetting(ctx, 'api', 'folder', apiFolder)

    @ts_setup.command(name='url')
    async def ts_setup_url(self, ctx, newURL):
        """Set API URL
        \rSetting the URL lets the bot know how to reach the Thoughts API.\r\r
        Example:
        `.tset api url https://mysite.com/thoughts`"""
        await self.changeSetting(ctx, 'api', 'url', newURL)

    @ts_api.command(name='shuffle')
    async def ts_api_shuffle(self, ctx, binary):
        """Shuffle search results by default
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'api', 'shuffle', binary)

    @ts_api.command(name='showid')
    async def ts_api_showid(self, ctx, binary):
        """Show IDs in search results
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'api', 'showID', binary)

    @ts_api.command(name='quotes')
    async def ts_api_quotes(self, ctx, quotes):
        """Quotes around each thought"""
        await self.changeSetting(ctx, 'api', 'quotes', quotes)

    @ts_api.command(name='breaks')
    async def ts_api_breaks(self, ctx, binary):
        """Receive <br /> instead of newlines
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'api', 'breaks', binary)

    @ts_api.command(name='create')
    async def ts_api_create(self, ctx, binary):
        """Allow new post creation to non-mods via API
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'api', 'create', binary)

    @ts_api.command(name='createflood')
    async def ts_api_createflood(self, ctx, time):
        """Creation flood time limit 
        \rSet how long between creating posts via the API that a user has to wait.\r\r
        Examples: 1s, 3m, 5d, 7w
        10 minutes would be `.tset api createflood 10m`\r\r
        This is separate from the flood limit on your bot users."""
        await self.changeSetting(ctx, 'api', 'createFlood', time)

    @ts_api.command(name='searchlimit')
    async def ts_api_searchlimit(self, ctx, newLimit):
        """Max search results that can appear via the API
        \rExample: `.tset api searchlimit 500"""
        await self.changeSetting(ctx, 'api', 'searchLimit', newLimit)

    @ts_api.command(name='searchresults')
    async def ts_api_searchresults(self, ctx, newLimit):
        """Default amount of search results
        \rExample: `.tset api searchresults 50"""
        await self.changeSetting(ctx, 'api', 'searchResults', newLimit)

    # Set -> API -> tags
    @ts_api.group(name='tags', aliases=['tag'])
    async def ts_api_tags(self, ctx):
        """Tag settings"""

    @ts_api_tags.command(name='default')
    async def ts_api_tagdefault(self, ctx, tag):
        """Default tag on new posts
        \rTag that will be used on newly created posts if none is set\r
        Example: `.tset api tagdefault mytag`"""
        await self.changeSetting(ctx, 'api', 'tagDefault', tag)

    @ts_api_tags.command(name='add')
    async def ts_api_tagadd(self, ctx, tag):
        """Add tag
        \rExample: `.tset api tag add newTagName`"""
        await self.changeTag(ctx, 'add', tag)

    @ts_api_tags.command(name='remove')
    async def ts_api_tagremove(self, ctx, tag):
        """Remove tag
        \rExample: `.tset api tag remove tagName`"""
        await self.changeTag(ctx, 'remove', tag)

    @ts_api_tags.command(name='list')
    async def ts_api_taglist(self, ctx):
        """List tags"""
        await self.changeTag(ctx, 'list')

    @ts_api_tags.command(name='rename')
    async def ts_api_tagrename(self, ctx, oldTag, newTag):
        """Rename tag
        \rThis will change all posts from one tag to another and remove the old tag.
        \rExample: `.tset api tag rename oldTag newTag`"""
        await self.changeTag(ctx, 'edit', oldTag, newTag)

    # end Set -> API -> tags

    @ts_bot.command(name='deletedreason')
    async def ts_bot_deletedreason(self, ctx, binary):
        """Show reason posts were deleted
        \rExample: `.tset bot deletedreason 1"""

        if binary == "1" or binary == "0":
            await self.config.deletedReason.set(binary)
            return await ctx.send("Set `bot deletedreason` to "+binary)
        else:
            return await ctx.send("Error: `bot deletedreason` must be a 1 or 0")
        
    @ts_bot.command(name='deletedby')
    async def ts_bot_deletedby(self, ctx, binary):
        """Show who deleted queried post
        \rExample: `.tset bot deletedby 1"""

        if binary == "1" or binary == "0":
            await self.config.deletedBy.set(binary)
            return await ctx.send("Set `bot deletedby` to "+binary)
        else:
            return await ctx.send("Error: `bot deletedby` must be a 1 or 0")

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

    # Theme Settings
    @ts_web.group(name='theme')
    async def ts_web_theme(self, ctx):
        """Tag settings"""

    @ts_web_theme.command(name='bg', aliases=['bgcolor', 'background', 'backgroundcolor'])
    async def ts_web_bgcolor(self, ctx, color):
        """Background color
        \rWebsite's background color.
        You can use anything the CSS color tag supports\r
        Example: `.tset web bgcolor #212121`"""
        await self.changeSetting(ctx, 'web', 'backgroundColor', color)

    @ts_web_theme.command(name='accent', aliases=['accentcolor'])
    async def ts_web_accentcolor(self, ctx, color):
        """Accent color
        \rWebsite's box color for API info, search, creation.
        You can use anything the CSS color tag supports\r
        Example: `.tset web themeaccent #393939`"""
        await self.changeSetting(ctx, 'web', 'accentColor', color)

    @ts_web_theme.command(name='font', aliases=['fontcolor'])
    async def ts_web_fontcolor(self, ctx, color):
        """Font color
        \rWebsite's font color.
        You can use anything the CSS color tag supports\r
        Example: `.tset web themefont #e9e5e5`"""
        await self.changeSetting(ctx, 'web', 'accentColor', color)

    @ts_web_theme.command(name='radius', aliases=['accentradius'])
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

    @ts_web.command(name='versionvisible')
    async def ts_web_createvis(self, ctx, binary):
        """Show Thoughts Web version in footer
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'versionVisible', binary)

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
        \rExample: `.tset web searchlimit 500"""
        await self.changeSetting(ctx, 'web', 'searchLimit', newLimit)

    @ts_web.command(name='searchresults')
    async def ts_web_searchresults(self, ctx, newLimit):
        """Default amount of search results
        \rExample: `.tset web searchresults 50"""
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