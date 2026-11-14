// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_pos_banquets_extras_extra_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerPosBanquetsExtrasExtraResponse
_$PatchV1OwnerPosBanquetsExtrasExtraResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1OwnerPosBanquetsExtrasExtraResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data135.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1OwnerPosBanquetsExtrasExtraResponseToJson(
  PatchV1OwnerPosBanquetsExtrasExtraResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
