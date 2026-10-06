import { HTTPError } from 'ky';

export async function getErrorMsg(error: unknown, defaultMsg?: string) {
  let messageText = defaultMsg || 'An Error has occurred!';
  if ('string' === typeof error) {
    messageText = error;
  } else if (error instanceof HTTPError) {
    // A proxy or WAF page (502, 504, block page) is HTML, not the REST JSON error.
    const errorData = await error.response
      .clone()
      .json()
      .catch(() => null);
    if (typeof errorData?.message === 'string' && errorData.message) {
      messageText = errorData.message;
    }
  } else if (error instanceof Error) {
    messageText = error.message;
  }
  return messageText;
}
