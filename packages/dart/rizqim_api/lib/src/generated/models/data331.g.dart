// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data331.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data331 _$Data331FromJson(Map<String, dynamic> json) => Data331(
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data331ToJson(Data331 instance) => <String, dynamic>{
  'slots': instance.slots,
};
