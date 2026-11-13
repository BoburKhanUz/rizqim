// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data192.g.dart';

@JsonSerializable()
class Data192 {
  const Data192({
    required this.requestId,
    required this.printerId,
    required this.targetDevice,
  });

  factory Data192.fromJson(Map<String, Object?> json) =>
      _$Data192FromJson(json);

  @JsonKey(name: 'request_id')
  final String requestId;
  @JsonKey(name: 'printer_id')
  final int? printerId;
  @JsonKey(name: 'target_device')
  final String? targetDevice;

  Map<String, Object?> toJson() => _$Data192ToJson(this);
}
