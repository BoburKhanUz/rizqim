// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sold_out2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

SoldOut2 _$SoldOut2FromJson(Map<String, dynamic> json) => SoldOut2(
  variants: (json['variants'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
);

Map<String, dynamic> _$SoldOut2ToJson(SoldOut2 instance) => <String, dynamic>{
  'variants': instance.variants,
};
