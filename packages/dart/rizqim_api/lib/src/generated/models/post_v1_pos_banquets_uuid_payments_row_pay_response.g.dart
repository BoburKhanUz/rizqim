// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_banquets_uuid_payments_row_pay_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBanquetsUuidPaymentsRowPayResponse
_$PostV1PosBanquetsUuidPaymentsRowPayResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBanquetsUuidPaymentsRowPayResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data235.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBanquetsUuidPaymentsRowPayResponseToJson(
  PostV1PosBanquetsUuidPaymentsRowPayResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
