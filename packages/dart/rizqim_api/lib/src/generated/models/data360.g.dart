// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data360.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data360 _$Data360FromJson(Map<String, dynamic> json) => Data360(
  voucher: Voucher2.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries12.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data360ToJson(Data360 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
