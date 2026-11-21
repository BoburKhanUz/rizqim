// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data355.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data355 _$Data355FromJson(Map<String, dynamic> json) => Data355(
  voucher: Voucher2.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries12.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data355ToJson(Data355 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
