from .thoughts import Thoughts

__red_end_user_data_statement__ = "This cog only stores data submitted by the user. Retrieving data is not logged."

def setup(bot):
    bot.add_cog(Thoughts(bot))
