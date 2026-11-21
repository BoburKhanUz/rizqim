// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data356.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data356 _$Data356FromJson(Map<String, dynamic> json) => Data356(
  voucher: Voucher2.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries12.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data356ToJson(Data356 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
