// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device6.dart';
import 'store.dart';
import 'branch2.dart';

part 'data10.g.dart';

@JsonSerializable()
class Data10 {
  const Data10({
    required this.token,
    required this.device,
    required this.store,
    required this.branch,
  });

  factory Data10.fromJson(Map<String, Object?> json) => _$Data10FromJson(json);

  final String token;
  final Device6 device;
  final Store? store;
  final Branch2? branch;

  Map<String, Object?> toJson() => _$Data10ToJson(this);
}
