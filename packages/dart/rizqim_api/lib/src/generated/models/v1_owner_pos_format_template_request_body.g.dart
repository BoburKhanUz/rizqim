// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_format_template_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosFormatTemplateRequestBody
_$V1OwnerPosFormatTemplateRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerPosFormatTemplateRequestBody(
      branchId: (json['branch_id'] as num?)?.toInt(),
      profile: json['profile'] as String?,
      stations: json['stations'] as bool?,
      menu: json['menu'] as bool?,
    );

Map<String, dynamic> _$V1OwnerPosFormatTemplateRequestBodyToJson(
  V1OwnerPosFormatTemplateRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'profile': instance.profile,
  'stations': instance.stations,
  'menu': instance.menu,
};
