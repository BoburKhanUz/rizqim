// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'vouchers4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Vouchers4 _$Vouchers4FromJson(Map<String, dynamic> json) => Vouchers4(
  sold: Sold4.fromJson(json['sold'] as Map<String, dynamic>),
  redeemed: Redeemed4.fromJson(json['redeemed'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Vouchers4ToJson(Vouchers4 instance) => <String, dynamic>{
  'sold': instance.sold,
  'redeemed': instance.redeemed,
};
