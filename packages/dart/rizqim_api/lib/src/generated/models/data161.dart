// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'dart:io';

import 'package:json_annotation/json_annotation.dart';

import 'file.dart';
import 'device10.dart';
import 'totals.dart';
import 'results.dart';
import 'skipped.dart';
import 'warnings.dart';

part 'data161.g.dart';

@JsonSerializable()
class Data161 {
  const Data161({
    required this.serverTime,
    required this.complete,
    required this.stoppedReason,
    required this.file,
    required this.device,
    required this.totals,
    required this.results,
    required this.skipped,
    required this.warnings,
  });

  factory Data161.fromJson(Map<String, Object?> json) =>
      _$Data161FromJson(json);

  @JsonKey(name: 'server_time')
  final String serverTime;
  final bool complete;
  @JsonKey(name: 'stopped_reason')
  final String? stoppedReason;
  final File file;
  final Device10 device;
  final Totals totals;
  final List<Results> results;
  final List<Skipped> skipped;
  final List<Warnings> warnings;

  Map<String, Object?> toJson() => _$Data161ToJson(this);
}
