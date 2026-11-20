// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data354.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data354 _$Data354FromJson(Map<String, dynamic> json) => Data354(
  voucher: Voucher2.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries12.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data354ToJson(Data354 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
