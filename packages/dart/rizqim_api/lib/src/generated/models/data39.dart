// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'write_off.dart';
import 'batch.dart';

part 'data39.g.dart';

@JsonSerializable()
class Data39 {
  const Data39({required this.writeOff, required this.batch});

  factory Data39.fromJson(Map<String, Object?> json) => _$Data39FromJson(json);

  @JsonKey(name: 'write_off')
  final WriteOff writeOff;
  final Batch batch;

  Map<String, Object?> toJson() => _$Data39ToJson(this);
}
