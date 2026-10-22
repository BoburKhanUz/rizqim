// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bills_uuid_print_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBillsUuidPrintRequestBody _$V1PosBillsUuidPrintRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBillsUuidPrintRequestBody(
  version: (json['version'] as num?)?.toInt(),
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosBillsUuidPrintRequestBodyToJson(
  V1PosBillsUuidPrintRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'approval_token': instance.approvalToken,
};
