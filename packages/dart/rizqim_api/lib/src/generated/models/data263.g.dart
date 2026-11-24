// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data263.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data263 _$Data263FromJson(Map<String, dynamic> json) => Data263(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  timezone: json['timezone'] as String,
  rooms: (json['rooms'] as List<dynamic>)
      .map((e) => Rooms.fromJson(e as Map<String, dynamic>))
      .toList(),
  unassigned: (json['unassigned'] as List<dynamic>)
      .map((e) => Unassigned.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data263ToJson(Data263 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'timezone': instance.timezone,
  'rooms': instance.rooms,
  'unassigned': instance.unassigned,
};
