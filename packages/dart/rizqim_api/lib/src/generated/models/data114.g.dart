// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data114.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data114 _$Data114FromJson(Map<String, dynamic> json) => Data114(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data114ToJson(Data114 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
