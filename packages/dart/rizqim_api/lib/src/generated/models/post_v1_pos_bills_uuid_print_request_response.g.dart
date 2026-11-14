// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bills_uuid_print_request_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBillsUuidPrintRequestResponse
_$PostV1PosBillsUuidPrintRequestResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosBillsUuidPrintRequestResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data250.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosBillsUuidPrintRequestResponseToJson(
  PostV1PosBillsUuidPrintRequestResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
