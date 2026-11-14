// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_banquets_uuid_staff_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBanquetsUuidStaffRequestBody _$V1PosBanquetsUuidStaffRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBanquetsUuidStaffRequestBody(
  userIds: (json['user_ids'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
);

Map<String, dynamic> _$V1PosBanquetsUuidStaffRequestBodyToJson(
  V1PosBanquetsUuidStaffRequestBody instance,
) => <String, dynamic>{'user_ids': instance.userIds};
