// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data218.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data218 _$Data218FromJson(Map<String, dynamic> json) => Data218(
  account: Account.fromJson(json['account'] as String),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data218ToJson(Data218 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
