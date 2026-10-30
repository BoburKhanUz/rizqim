// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data162.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data162 _$Data162FromJson(Map<String, dynamic> json) => Data162(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data162ToJson(Data162 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
