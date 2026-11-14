// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_banquets_uuid_payments_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBanquetsUuidPaymentsRequestBody
_$V1PosBanquetsUuidPaymentsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosBanquetsUuidPaymentsRequestBody(
      rows: (json['rows'] as List<dynamic>)
          .map((e) => Rows9.fromJson(e as Map<String, dynamic>))
          .toList(),
      version: (json['version'] as num?)?.toInt(),
    );

Map<String, dynamic> _$V1PosBanquetsUuidPaymentsRequestBodyToJson(
  V1PosBanquetsUuidPaymentsRequestBody instance,
) => <String, dynamic>{'rows': instance.rows, 'version': instance.version};
