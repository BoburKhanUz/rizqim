// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_sync_errors_op_id_resolve_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosSyncErrorsOpIdResolveResponse
_$PostV1PosSyncErrorsOpIdResolveResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosSyncErrorsOpIdResolveResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data201.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosSyncErrorsOpIdResolveResponseToJson(
  PostV1PosSyncErrorsOpIdResolveResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
