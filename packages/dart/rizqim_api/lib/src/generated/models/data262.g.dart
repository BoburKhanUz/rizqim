// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data262.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data262 _$Data262FromJson(Map<String, dynamic> json) => Data262(
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

Map<String, dynamic> _$Data262ToJson(Data262 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'timezone': instance.timezone,
  'rooms': instance.rooms,
  'unassigned': instance.unassigned,
};
