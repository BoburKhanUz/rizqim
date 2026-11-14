// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_pos_banquets_uuid_payments_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1PosBanquetsUuidPaymentsResponse
_$PutV1PosBanquetsUuidPaymentsResponseFromJson(Map<String, dynamic> json) =>
    PutV1PosBanquetsUuidPaymentsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data233.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1PosBanquetsUuidPaymentsResponseToJson(
  PutV1PosBanquetsUuidPaymentsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
