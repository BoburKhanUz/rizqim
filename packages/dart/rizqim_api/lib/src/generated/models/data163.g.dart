// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data163.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data163 _$Data163FromJson(Map<String, dynamic> json) => Data163(
  account: Account.fromJson(json['account'] as String),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data163ToJson(Data163 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
