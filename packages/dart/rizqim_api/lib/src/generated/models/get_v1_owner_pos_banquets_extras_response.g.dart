// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_banquets_extras_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosBanquetsExtrasResponse
_$GetV1OwnerPosBanquetsExtrasResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerPosBanquetsExtrasResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data132.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerPosBanquetsExtrasResponseToJson(
  GetV1OwnerPosBanquetsExtrasResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
