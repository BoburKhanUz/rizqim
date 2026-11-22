// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data360.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data360 _$Data360FromJson(Map<String, dynamic> json) => Data360(
  branchId: (json['branch_id'] as num).toInt(),
  date: json['date'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries13.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary14.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data360ToJson(Data360 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date,
  'entries': instance.entries,
  'summary': instance.summary,
};
