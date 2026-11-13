// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data300.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data300 _$Data300FromJson(Map<String, dynamic> json) => Data300(
  voucher: Voucher2.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries6.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data300ToJson(Data300 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
