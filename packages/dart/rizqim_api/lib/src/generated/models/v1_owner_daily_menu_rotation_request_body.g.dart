// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_daily_menu_rotation_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerDailyMenuRotationRequestBody
_$V1OwnerDailyMenuRotationRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerDailyMenuRotationRequestBody(
      productIds: (json['product_ids'] as List<dynamic>)
          .map((e) => (e as num).toInt())
          .toList(),
    );

Map<String, dynamic> _$V1OwnerDailyMenuRotationRequestBodyToJson(
  V1OwnerDailyMenuRotationRequestBody instance,
) => <String, dynamic>{'product_ids': instance.productIds};
