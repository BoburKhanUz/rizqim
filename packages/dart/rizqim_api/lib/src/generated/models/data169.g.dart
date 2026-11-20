// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data169.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data169 _$Data169FromJson(Map<String, dynamic> json) => Data169(
  vouchers: (json['vouchers'] as List<dynamic>)
      .map((e) => Vouchers.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data169ToJson(Data169 instance) => <String, dynamic>{
  'vouchers': instance.vouchers,
  'meta': instance.meta,
};
