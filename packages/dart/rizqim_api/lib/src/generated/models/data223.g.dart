// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data223.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data223 _$Data223FromJson(Map<String, dynamic> json) => Data223(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  timezone: json['timezone'] as String,
  slots: Slots7.fromJson(json['slots'] as Map<String, dynamic>),
  halls: (json['halls'] as List<dynamic>)
      .map((e) => Halls5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data223ToJson(Data223 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'timezone': instance.timezone,
  'slots': instance.slots,
  'halls': instance.halls,
};
