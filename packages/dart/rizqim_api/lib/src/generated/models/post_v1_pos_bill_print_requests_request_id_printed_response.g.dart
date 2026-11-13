// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bill_print_requests_request_id_printed_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBillPrintRequestsRequestIdPrintedResponse
_$PostV1PosBillPrintRequestsRequestIdPrintedResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBillPrintRequestsRequestIdPrintedResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data188.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBillPrintRequestsRequestIdPrintedResponseToJson(
  PostV1PosBillPrintRequestsRequestIdPrintedResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
