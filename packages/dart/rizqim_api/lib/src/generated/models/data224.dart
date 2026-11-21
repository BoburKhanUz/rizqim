// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'packages2.dart';
import 'extras3.dart';
import 'halls6.dart';
import 'settings6.dart';
import 'event_types2.dart';

part 'data224.g.dart';

@JsonSerializable()
class Data224 {
  const Data224({
    required this.packages,
    required this.extras,
    required this.halls,
    required this.settings,
    required this.eventTypes,
  });

  factory Data224.fromJson(Map<String, Object?> json) =>
      _$Data224FromJson(json);

  final List<Packages2> packages;
  final List<Extras3> extras;
  final List<Halls6> halls;
  final Settings6 settings;
  @JsonKey(name: 'event_types')
  final List<EventTypes2> eventTypes;

  Map<String, Object?> toJson() => _$Data224ToJson(this);
}
