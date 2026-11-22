// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data359.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data359 _$Data359FromJson(Map<String, dynamic> json) => Data359(
  branchId: (json['branch_id'] as num).toInt(),
  date: json['date'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries13.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary14.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data359ToJson(Data359 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date,
  'entries': instance.entries,
  'summary': instance.summary,
};
