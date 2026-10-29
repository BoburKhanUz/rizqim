// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'write_off.dart';
import 'batch.dart';

part 'data38.g.dart';

@JsonSerializable()
class Data38 {
  const Data38({required this.writeOff, required this.batch});

  factory Data38.fromJson(Map<String, Object?> json) => _$Data38FromJson(json);

  @JsonKey(name: 'write_off')
  final WriteOff writeOff;
  final Batch batch;

  Map<String, Object?> toJson() => _$Data38ToJson(this);
}
