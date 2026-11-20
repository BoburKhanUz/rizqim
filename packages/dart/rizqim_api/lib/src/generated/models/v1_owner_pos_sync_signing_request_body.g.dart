// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_sync_signing_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosSyncSigningRequestBody _$V1OwnerPosSyncSigningRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerPosSyncSigningRequestBody(
  enforce: json['enforce'] as bool,
  confirmUnsigned: json['confirm_unsigned'] as bool?,
);

Map<String, dynamic> _$V1OwnerPosSyncSigningRequestBodyToJson(
  V1OwnerPosSyncSigningRequestBody instance,
) => <String, dynamic>{
  'enforce': instance.enforce,
  'confirm_unsigned': instance.confirmUnsigned,
};
