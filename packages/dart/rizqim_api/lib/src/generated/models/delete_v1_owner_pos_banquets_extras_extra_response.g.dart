// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_pos_banquets_extras_extra_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerPosBanquetsExtrasExtraResponse
_$DeleteV1OwnerPosBanquetsExtrasExtraResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerPosBanquetsExtrasExtraResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data134.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$DeleteV1OwnerPosBanquetsExtrasExtraResponseToJson(
  DeleteV1OwnerPosBanquetsExtrasExtraResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
