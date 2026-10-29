// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_daily_menu_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerDailyMenuRequestBody _$V1OwnerDailyMenuRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerDailyMenuRequestBody(
  productIds: (json['product_ids'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  date: json['date'] == null ? null : DateTime.parse(json['date'] as String),
);

Map<String, dynamic> _$V1OwnerDailyMenuRequestBodyToJson(
  V1OwnerDailyMenuRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date?.toIso8601String(),
  'product_ids': instance.productIds,
};
