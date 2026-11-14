// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_uuid_cancellation_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsUuidCancellationResponse
_$GetV1PosBanquetsUuidCancellationResponseFromJson(Map<String, dynamic> json) =>
    GetV1PosBanquetsUuidCancellationResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data226.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1PosBanquetsUuidCancellationResponseToJson(
  GetV1PosBanquetsUuidCancellationResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
