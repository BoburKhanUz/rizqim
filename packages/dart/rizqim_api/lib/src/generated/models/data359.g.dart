// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data359.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data359 _$Data359FromJson(Map<String, dynamic> json) => Data359(
  voucher: Voucher2.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries12.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data359ToJson(Data359 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
