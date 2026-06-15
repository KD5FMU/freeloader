This is an upload utilty called freeloader because it frees the user from having to use SSH or SFTP to upload files to your node for use.
Here is how to install it:
first lets get to s specific directory after you after started an SSH session into your node or from the terminal CLI if you like. 

```
cd /etc/asterisk/local
```

if the directory does not exist, lets create it

```
sudo mkdir /etc/asterisk/local
```

then switch to the directory

```
cd /etc/asterisk/local
```

Then lets download the installer script

```
sudo wget https://raw.githubusercontent.com/n5ad/freeloader/refs/heads/main/freeloader.sh
```
Then lets run the script using
```
sudo bash freeloader.sh
```


<img width="1920" height="1032" alt="image" src="https://github.com/user-attachments/assets/16220077-19d9-4e58-bf36-79fc9fb4ab53" />
