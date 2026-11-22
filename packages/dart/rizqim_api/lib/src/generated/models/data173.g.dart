// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data173.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data173 _$Data173FromJson(Map<String, dynamic> json) => Data173(
  vouchers: (json['vouchers'] as List<dynamic>)
      .map((e) => Vouchers.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data173ToJson(Data173 instance) => <String, dynamic>{
  'vouchers': instance.vouchers,
  'meta': instance.meta,
};
