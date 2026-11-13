// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'vouchers5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Vouchers5 _$Vouchers5FromJson(Map<String, dynamic> json) => Vouchers5(
  sold: Sold5.fromJson(json['sold'] as Map<String, dynamic>),
  redeemed: Redeemed5.fromJson(json['redeemed'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Vouchers5ToJson(Vouchers5 instance) => <String, dynamic>{
  'sold': instance.sold,
  'redeemed': instance.redeemed,
};
