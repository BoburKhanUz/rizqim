// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data358.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data358 _$Data358FromJson(Map<String, dynamic> json) => Data358(
  voucher: Voucher2.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries12.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data358ToJson(Data358 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
