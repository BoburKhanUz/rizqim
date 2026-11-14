// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rows9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rows9 _$Rows9FromJson(Map<String, dynamic> json) => Rows9(
  kind: Kind3.fromJson(json['kind'] as String),
  dueDate: DateTime.parse(json['due_date'] as String),
  amount: json['amount'] as num,
  uuid: json['uuid'] as String?,
);

Map<String, dynamic> _$Rows9ToJson(Rows9 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'kind': instance.kind,
  'due_date': instance.dueDate.toIso8601String(),
  'amount': instance.amount,
};
