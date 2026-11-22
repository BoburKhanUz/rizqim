// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data221.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data221 _$Data221FromJson(Map<String, dynamic> json) => Data221(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries3.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary4.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data221ToJson(Data221 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'entries': instance.entries,
  'summary': instance.summary,
};
