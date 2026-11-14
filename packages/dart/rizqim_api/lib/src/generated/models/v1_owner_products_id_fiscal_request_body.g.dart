// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_products_id_fiscal_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerProductsIdFiscalRequestBody _$V1OwnerProductsIdFiscalRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerProductsIdFiscalRequestBody(
  kind: json['kind'] == null ? null : Kind4.fromJson(json['kind'] as String?),
  mxikCode: json['mxik_code'] as String?,
  packageCode: json['package_code'] as String?,
  vatRate: json['vat_rate'] as num?,
  variants: (json['variants'] as List<dynamic>?)
      ?.map((e) => Variants2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$V1OwnerProductsIdFiscalRequestBodyToJson(
  V1OwnerProductsIdFiscalRequestBody instance,
) => <String, dynamic>{
  'kind': instance.kind,
  'mxik_code': instance.mxikCode,
  'package_code': instance.packageCode,
  'vat_rate': instance.vatRate,
  'variants': instance.variants,
};
