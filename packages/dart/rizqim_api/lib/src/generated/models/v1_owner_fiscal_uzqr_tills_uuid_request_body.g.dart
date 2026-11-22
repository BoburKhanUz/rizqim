// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_fiscal_uzqr_tills_uuid_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerFiscalUzqrTillsUuidRequestBody
_$V1OwnerFiscalUzqrTillsUuidRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerFiscalUzqrTillsUuidRequestBody(
      apiKey: json['api_key'] as String?,
      removeApiKey: json['remove_api_key'] as bool?,
    );

Map<String, dynamic> _$V1OwnerFiscalUzqrTillsUuidRequestBodyToJson(
  V1OwnerFiscalUzqrTillsUuidRequestBody instance,
) => <String, dynamic>{
  'api_key': instance.apiKey,
  'remove_api_key': instance.removeApiKey,
};
