import Toastify from 'toastify-js';

const Toast = {
  
  defaults : {},
  
  parseOptions : ( options ) => {
    // parse the provided options with our defaults
  },
  
  /**
   * Initiate an error toast
   * 
   * @param text
   * @param options
   * @returns Toastify
   */
  error : ( text, options = {} ) => {
    options.className = 'cp-toast--error cp-toast';
    options.text = text;
//    options.duration = -1;
    
    let toast = Toastify(options);
    toast.showToast();
    return toast;
  },

}

export default Toast;