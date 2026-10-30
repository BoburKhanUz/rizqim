// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'participants.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Participants _$ParticipantsFromJson(Map<String, dynamic> json) => Participants(
  staffId: (json['staff_id'] as num).toInt(),
  minutes: (json['minutes'] as num?)?.toInt(),
);

Map<String, dynamic> _$ParticipantsToJson(Participants instance) =>
    <String, dynamic>{
      'staff_id': instance.staffId,
      'minutes': instance.minutes,
    };
