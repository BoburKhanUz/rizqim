// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_uuid_payments_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsUuidPaymentsResponse
_$GetV1PosBanquetsUuidPaymentsResponseFromJson(Map<String, dynamic> json) =>
    GetV1PosBanquetsUuidPaymentsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data232.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1PosBanquetsUuidPaymentsResponseToJson(
  GetV1PosBanquetsUuidPaymentsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
