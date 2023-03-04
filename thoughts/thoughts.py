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
            "apiFolder" :  "api",
            "limit": 3, # also known as searchResults
            "shuffle": 0,
            "showAuthor": 1,
            "showID": 0
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
                async with aiohttp.request("GET", current_url+"/"+str(apiFolder)+"?f=config&key1="+key1+"&key2="+key2+"&val="+newVal+"&version="+str(self.versionapi)+"&versionbot="+str(self.versionbot)+"&platform=discord&token="+str(current_token), headers={"Accept": "text/plain"}) as r:
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
            async with aiohttp.request("GET", current_url+"/"+str(apiFolder)+"?f=tags&s="+str(query)+"&tag="+str(tag1)+"&authorID="+str(authorID)+"&rename="+str(tag2)+"&version="+str(self.versionapi)+"&versionbot="+str(self.versionbot)+"&platform=discord&token="+str(current_token), headers={"Accept": "text/plain"}) as r:
                if r.status != 200:
                    return await ctx.send("Oops! Cannot make tag request...")
                result = await r.text(encoding="UTF-8")
        except aiohttp.ClientConnectionError:
            return await ctx.send("Oops! Cannot make tag request...")

        await ctx.send(f"{result}")


    @commands.command(aliases=['thoughts'])
    async def thought(self, ctx, search=""):
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
        shuffle = await self.config.shuffle()
        showAuthor = await self.config.showAuthor()
        showID = await self.config.showID()
        limit = await self.config.limit()

        if current_url == '':
            return await ctx.send("You need to set an API URL. Type `.tset setup url`")
        
        deleted_reason = await self.config.deletedReason() # whether or not to show deleted reason
        deleted_by = await self.config.deletedBy() # whether or not to show who deleted post
        apiFolder = await self.config.apiFolder()

        try:
            async with aiohttp.request("GET", current_url+"/"+str(apiFolder)+"?f=search&token="+current_token+"&s="+search+"&limit="+str(limit)+"&shuffle="+str(shuffle)+"&showAuthor="+str(showAuthor)+"&showID="+str(showID)+"&reason="+str(deleted_reason)+"&reasonby="+str(deleted_by)+"&platform=discord&version="+str(self.versionapi)+"&versionbot="+str(self.versionbot), headers={"Accept": "text/plain"}) as r:
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
            async with aiohttp.request("GET", current_url+"/"+str(apiFolder)+"?f=create&token="+current_token+"&platform=discord&authorID="+str(tAuthorID)+"&tag="+tag+"&msg="+tBaseString+"&version="+str(self.versionapi)+"&versionbot="+str(self.versionbot)+"&author="+tABaseString, headers={"Accept": "text/plain"}) as r:
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
            async with aiohttp.request("GET", current_url+"/"+str(apiFolder)+"?f=delete&token="+current_token+"&platform=discord&id="+str(id)+"&deleterID="+str(deleterID)+"&reason="+reason+"&version="+str(self.versionapi)+"&versionbot="+str(self.versionbot)+"&deleter="+deleter, headers={"Accept": "text/plain"}) as r:
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

    @thoughtset.command(name='list')
    async def ts_list(self, ctx):
        """List all config settings"""
        await self.changeSetting(ctx, 'list', 'none', 'none')

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

    @ts_api.command(name='showID')
    async def ts_api_showid(self, ctx, binary):
        """Show IDs in search results
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'api', 'showID', binary)

    @ts_api.command(name='showAuthor')
    async def ts_api_showauthor(self, ctx, binary):
        """Show post author in search results
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'api', 'showAuthor', binary)

    @ts_api.command(name='wrap')
    async def ts_api_wrap(self, ctx, wrap):
        """Wrap (quotes) around each thought"""
        await self.changeSetting(ctx, 'api', 'wrap', wrap)

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

    @ts_api.command(name='createFlood')
    async def ts_api_createflood(self, ctx, time):
        """Creation flood time limit 
        \rSet how long between creating posts via the API that a user has to wait.\r\r
        Examples: 1s, 3m, 5d, 7w
        10 minutes would be `.tset api createflood 10m`\r\r
        This is separate from the flood limit on your bot users."""
        await self.changeSetting(ctx, 'api', 'createFlood', time)

    @ts_api.command(name='searchLimit')
    async def ts_api_searchlimit(self, ctx, newLimit):
        """Max search results that can appear via the API
        \rExample: `.tset api searchlimit 500"""
        await self.changeSetting(ctx, 'api', 'searchLimit', newLimit)

    @ts_api.command(name='searchResults')
    async def ts_api_searchresults(self, ctx, newLimit):
        """Default amount of search results
        \rExample: `.tset api searchresults 50"""
        await self.changeSetting(ctx, 'api', 'searchResults', newLimit)

    @ts_api.command(name='ipLog')
    async def ts_api_iplog(self, ctx, binary):
        """Log IP address of post creator
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'api', 'ipLog', binary)

    @ts_api.command(name='ipHash')
    async def ts_api_iphash(self, ctx, binary):
        """Hash IP addresses
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'api', 'ipHash', binary)

    @ts_api.command(name='cli')
    async def ts_api_cli(self, ctx, binary):
        """Enable CLI of API .php file
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'api', 'cli', binary)

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

    @ts_bot.command(name='deletedReason')
    async def ts_bot_deletedreason(self, ctx, binary):
        """Show reason posts were deleted
        \rExample: `.tset bot deletedreason 1"""

        if binary == "1" or binary == "0":
            await self.config.deletedReason.set(binary)
            return await ctx.send("Set `bot deletedreason` to "+binary)
        else:
            return await ctx.send("Error: `bot deletedreason` must be a 1 or 0")
        
    @ts_bot.command(name='deletedBy')
    async def ts_bot_deletedby(self, ctx, binary):
        """Show who deleted queried post
        \rExample: `.tset bot deletedby 1"""

        if binary == "1" or binary == "0":
            await self.config.deletedBy.set(binary)
            return await ctx.send("Set `bot deletedby` to "+binary)
        else:
            return await ctx.send("Error: `bot deletedby` must be a 1 or 0")
        
    @ts_bot.command(name='shuffle')
    async def ts_bot_shuffle(self, ctx, binary):
        """Shuffle search results
        \rExample: `.tset bot shuffle 1"""

        if binary == "1" or binary == "0":
            await self.config.shuffle.set(binary)
            return await ctx.send("Set `bot shuffle` to "+binary)
        else:
            return await ctx.send("Error: `bot shuffle` must be a 1 or 0")
        
    @ts_bot.command(name='showAuthor')
    async def ts_bot_showAuthor(self, ctx, binary):
        """Show author of posts
        \rExample: `.tset bot showAuthor 1"""

        if binary == "1" or binary == "0":
            await self.config.showAuthor.set(binary)
            return await ctx.send("Set `bot showAuthor` to "+binary)
        else:
            return await ctx.send("Error: `bot showAuthor` must be a 1 or 0")
        
    @ts_bot.command(name='showID')
    async def ts_bot_showID(self, ctx, binary):
        """Show ID of posts
        \rExample: `.tset bot showID 1"""

        if binary == "1" or binary == "0":
            await self.config.showID.set(binary)
            return await ctx.send("Set `bot showID` to "+binary)
        else:
            return await ctx.send("Error: `bot showID` must be a 1 or 0")
        
    @ts_bot.command(name='limit')
    async def ts_bot_limit(self, ctx, limit):
        """Amount of results per search
        \rExample: `.tset bot limit 3"""

        await self.config.limit.set(limit)
        return await ctx.send("Set `bot limit` to "+limit)
        

    @ts_web.command(name='shuffle')
    async def ts_web_shuffle(self, ctx, binary):
        """Shuffle search results by default
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'shuffle', binary)

    @ts_web.command(name='showID')
    async def ts_web_showid(self, ctx, binary):
        """Show IDs in search results
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'showID', binary)

    @ts_web.command(name='showAuthor')
    async def ts_web_showauthor(self, ctx, binary):
        """Show author of posts
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'showAuthor', binary)

    @ts_web.command(name='wrap')
    async def ts_web_wrap(self, ctx, wrap):
        """Wrap (quotes) around each thought"""
        await self.changeSetting(ctx, 'web', 'wrap', wrap)

    # Theme Settings
    @ts_web.group(name='theme')
    async def ts_web_theme(self, ctx):
        """Tag settings"""

    @ts_web_theme.command(name='bg', aliases=['bgcolor'])
    async def ts_web_bgcolor(self, ctx, color):
        """Background color
        \rWebsite's background color.
        You can use anything the CSS color tag supports\r
        Example: `.tset web theme bgcolor #212121`"""
        await self.changeSetting(ctx, 'theme', 'backgroundColor', color)

    @ts_web_theme.command(name='accent', aliases=['accentcolor'])
    async def ts_web_accentcolor(self, ctx, color):
        """Accent color
        \rWebsite's box color for API info, search, creation.
        You can use anything the CSS color tag supports\r
        Example: `.tset web theme accent #393939`"""
        await self.changeSetting(ctx, 'theme', 'accentColor', color)

    @ts_web_theme.command(name='font', aliases=['fontcolor'])
    async def ts_web_fontcolor(self, ctx, color):
        """Font color
        \rWebsite's font color.
        You can use anything the CSS color tag supports\r
        Example: `.tset web theme font #e9e5e5`"""
        await self.changeSetting(ctx, 'theme', 'accentColor', color)

    @ts_web_theme.command(name='url', aliases=['urlcolor'])
    async def ts_web_url(self, ctx, color):
        """URL color
        \rWebsite's URL color.
        You can use anything the CSS color tag supports\r
        Example: `.tset web theme url #e9e5e5`"""
        await self.changeSetting(ctx, 'theme', 'urlColor', color)

    @ts_web_theme.command(name='fontSize', aliases=['size'])
    async def ts_web_fontsize(self, ctx, size):
        """Font size
        \rWebsite's font size.
        You can use anything the CSS color tag supports\r
        Example: `.tset web theme fontsize #e9e5e5`"""
        await self.changeSetting(ctx, 'theme', 'fontSize', size)

    @ts_web_theme.command(name='radius', aliases=['accentRadius'])
    async def ts_web_accentradius(self, ctx, radius):
        """Accent box border radius
        \rWebsite's box border radius (roundness)
        You can use anything the CSS border-radius tag supports\r
        Example: `.tset web theme radius 10px`"""
        await self.changeSetting(ctx, 'theme', 'accentRadius', radius)

    @ts_web.command(name='create')
    async def ts_web_create(self, ctx, binary):
        """Enable Create Box
        \rAllow thought creation on the website.
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'create', binary)

    @ts_web.command(name='createVisible')
    async def ts_web_createvis(self, ctx, binary):
        """Show create box by default
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'createVisible', binary)

    @ts_web.command(name='versionVisible')
    async def ts_web_createvis(self, ctx, binary):
        """Show Thoughts Web version in footer
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'versionVisible', binary)

    @ts_web.command(name='createFlood')
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

    @ts_web.command(name='infoVisible')
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

    @ts_web.command(name='searchVisible')
    async def ts_web_searchvis(self, ctx, binary):
        """Show search box by default
        \rValue can be 1 or 0"""
        await self.changeSetting(ctx, 'web', 'searchVisible', binary)

    @ts_web.command(name='searchLimit')
    async def ts_web_searchlimit(self, ctx, newLimit):
        """Max search results that can appear on the website
        \rExample: `.tset web searchlimit 500"""
        await self.changeSetting(ctx, 'web', 'searchLimit', newLimit)

    @ts_web.command(name='searchResults')
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