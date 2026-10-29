// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bills_uuid_payments_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBillsUuidPaymentsResponse _$PostV1PosBillsUuidPaymentsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBillsUuidPaymentsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data139.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBillsUuidPaymentsResponseToJson(
  PostV1PosBillsUuidPaymentsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
