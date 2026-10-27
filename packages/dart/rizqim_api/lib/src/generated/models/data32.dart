// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device9.dart';
import 'store2.dart';
import 'branch2.dart';

part 'data32.g.dart';

@JsonSerializable()
class Data32 {
  const Data32({
    required this.token,
    required this.device,
    required this.store,
    required this.branch,
  });

  factory Data32.fromJson(Map<String, Object?> json) => _$Data32FromJson(json);

  final String token;
  final Device9 device;
  final Store2? store;
  final Branch2? branch;

  Map<String, Object?> toJson() => _$Data32ToJson(this);
}
