// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data352.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data352 _$Data352FromJson(Map<String, dynamic> json) => Data352(
  voucher: Voucher2.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries12.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data352ToJson(Data352 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
